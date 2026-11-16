#!/usr/bin/env python3
"""Contraste do modo noturno e ausência de painéis claros nas principais páginas.
Verifica as cores de referência segundo WCAG 2.1 (mínimo 4,5:1 para texto comum).
Não altera o modo claro, APIs nem dados pessoais.
"""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
CSS=(ROOT/"public/css/modo_noturno.css").read_text(encoding="utf-8")
ROUTER=(ROOT/"public/api/auth/pagina.php").read_text(encoding="utf-8")
assert CSS==(ROOT/"private/app/css/modo_noturno.css").read_text(encoding="utf-8")
assert "modo_noturno.css?v=20261110-dark2" in ROUTER,"Nova versão deve invalidar cache antigo."
assert CSS.count("{")==CSS.count("}")

def luminance(hexa):
    channels=[int(hexa[i:i+2],16)/255 for i in (1,3,5)]
    lin=[c/12.92 if c <= 0.04045 else ((c+0.055)/1.055)**2.4 for c in channels]
    return .2126*lin[0]+.7152*lin[1]+.0722*lin[2]
def contrast(a,b):
    high,low=sorted((luminance(a),luminance(b)),reverse=True)
    return (high+.05)/(low+.05)
COLORS={
    "main-background":"#0b1423",
    "card-background":"#17263d",
    "nested-background":"#0f1e32",
    "hover-background":"#253d5c",
    "text":"#eef4ff",
    "secondary-text":"#bfcee2",
    "success-background":"#173e37",
    "success-text":"#a3efcb",
    "warning-background":"#49381f",
    "warning-text":"#ffdd94",
    "error-background":"#4a2633",
    "error-text":"#ffc1c8",
    "button-background":"#243954",
    "button-text":"#e8f2ff",
}
for label,bg,fg in [
    ("Texto principal","main-background","text"),
    ("Texto no cartão","card-background","text"),
    ("Legenda no cartão","card-background","secondary-text"),
    ("Texto em fundo profundo","nested-background","text"),
    ("Texto no hover","hover-background","text"),
    ("Status sucesso","success-background","success-text"),
    ("Status atenção","warning-background","warning-text"),
    ("Status erro","error-background","error-text"),
    ("Botão secundário","button-background","button-text"),
]:
    ratio=contrast(COLORS[bg],COLORS[fg])
    assert ratio>=4.5,(label,round(ratio,2))
for label in ("admin-quick-card","dashboard-empty-state","secretary-panel",
              "secretary-quick-card","activities-panel","activity-modal-card",
              "submission-item","submissions-panel","correction-card",
              "student-file-item","pw-chat-preview-dialog","secretary-chat",
              "secretary-message","pw-chat-pinned-banner",
              "pw-chat-attachment-card","teacher-notification-item",
              "guardian-notice","secretary-notification-item",
              "teacher-class-card","enrollment-current","pwcal-panel","pwcal-day",
              "calendar-day","student-detail-grid > div","teacher-detail-grid > div",
              "guardian-detail-grid > div","class-detail-grid > div",
              "student-view-item","class-view-item","subject-view-item",
              "settings-security-note","pw-intelligent-notice"):
    assert "."+label in CSS,label

# Todas as regras de novos fundos precisam de escopo explícito de modo escuro.
section=CSS.split("MODO NOTURNO — COBERTURA DOS MÓDULOS LEGADOS",1)
assert len(section)==2
rules=re.findall(r'([^{}]+)\{([^{}]*)\}',section[1],flags=re.S)
for selector,decl in rules:
    selector=re.sub(r'/\*.*?\*/','',selector,flags=re.S).strip()
    if not selector or selector.startswith('@'):continue
    if "background:" in decl or "background-color:" in decl:
        assert 'html[data-primeway-theme="dark"]' in selector,selector[:150]

# A camada noturna não modifica imagens, fotos nem arquivos originais.
assert "filter:invert(" not in CSS
assert "body img" in CSS and "filter:none!important" in CSS
print("Modo noturno: contraste >= 4.5:1, superfícies legíveis, cache e modo claro preservados.")
