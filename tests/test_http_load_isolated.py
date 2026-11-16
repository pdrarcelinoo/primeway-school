#!/usr/bin/env python3
"""Carga pequena, previsível e ISOLADA no container de CI.

Não executa contra domínio público, não faz escrita e não usa contas reais.
Mede o impacto do MaxRequestWorkers atual em requisições estáticas, autenticação
sem sessão e redirecionamento privado, sem alterar o limite em produção.
"""
from concurrent.futures import ThreadPoolExecutor,as_completed
from statistics import median
from time import perf_counter
from urllib.error import HTTPError
from urllib.request import Request,urlopen
import os, json

base=os.environ.get("PRIMEWAY_TEST_URL","")
assert base in ("http://127.0.0.1:18080","http://localhost:18080"), \
    "Carga externa e testes sobre produção são proibidos."
cases=[
    ("/",200),
    ("/pages/dashboard.html",302),
    ("/api/auth/session.php",200),
]
def single(case):
    path,want=case
    start=perf_counter()
    try:
        # Redirects são verificados via HTTPError por opener sem redirect.
        from urllib.request import build_opener,HTTPRedirectHandler
        class NoRedirect(HTTPRedirectHandler):
            def redirect_request(self,req,fp,code,msg,headers,newurl):return None
        opener=build_opener(NoRedirect())
        try:
            response=opener.open(Request(base+path,headers={"Accept":"application/json"}),timeout=12)
        except HTTPError as error:
            response=error
        with response:
            status=response.status
            response.read(100_000)
        ok=status==want
    except Exception:
        ok=False
        status=-1
    return perf_counter()-start,ok,status,path

results=[]
for concurrency in (2,6,12):
    jobs=[cases[n%len(cases)] for n in range(96)]
    with ThreadPoolExecutor(max_workers=concurrency) as executor:
        samples=list(executor.map(single,jobs))
    times=sorted(x[0] for x in samples)
    failures=[(x[2],x[3]) for x in samples if not x[1]]
    p95=times[int((len(times)-1)*0.95)]
    row={"concurrency":concurrency,"requests":len(samples),
         "failures":len(failures),"median_ms":round(median(times)*1000,1),
         "p95_ms":round(p95*1000,1)}
    print(json.dumps(row))
    assert not failures, f"Respostas inválidas ou timeout: {failures[:8]}"
    assert p95<10, f"Latência p95 excessiva na cópia descartável: {p95:.2f}s"
    results.append(row)
assert sum(x["requests"] for x in results)==288
print("Carga HTTP isolada aprovada; não representa teste com centenas de sessões autenticadas.")
