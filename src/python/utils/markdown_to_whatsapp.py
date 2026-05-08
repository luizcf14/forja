"""
Conversor de Markdown para formatação do WhatsApp.

WhatsApp suporta um subconjunto limitado de formatação:
  *texto*   → negrito
  _texto_   → itálico
  ~texto~   → tachado
  ```texto``` → monoespaço (bloco de código)
  `texto`   → monoespaço inline

O que NÃO existe no WhatsApp:
  - Cabeçalhos (#, ##, ###) → convertidos para *negrito*
  - Links [texto](url)      → "texto (url)"
  - Imagens ![alt](url)     → removidas
  - Tabelas                 → convertidas para texto simples
  - Linhas horizontais      → removidas
  - Citações (> texto)      → "❝ texto"
  - **negrito** duplo       → *negrito* simples
  - ~~tachado~~ duplo       → ~tachado~ simples
"""

import re
from typing import Optional


# ---------------------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------------------

def _protect_code_blocks(text: str) -> tuple[str, dict]:
    """
    Extrai blocos de código (``` ... ```) e os substitui por tokens
    para que não sejam alterados pelas demais regexes.
    Retorna o texto com tokens e o mapa de restauração.
    """
    placeholders: dict[str, str] = {}
    counter = [0]

    def replace_block(m: re.Match) -> str:
        token = f"\x00CODE{counter[0]}\x00"
        counter[0] += 1
        # Remove o identificador de linguagem da primeira linha (```python → ```)
        content = m.group(1).strip()
        placeholders[token] = f"```\n{content}\n```"
        return token

    # Bloco de código com ou sem linguagem: ```python\n...\n```
    text = re.sub(r"```[a-zA-Z]*\n?(.*?)```", replace_block, text, flags=re.DOTALL)

    return text, placeholders


def _protect_inline_code(text: str, placeholders: dict) -> str:
    """Extrai código inline (`texto`) para protegê-lo."""
    counter = [max((int(k[5:-1]) for k in placeholders), default=-1) + 1]

    def replace_inline(m: re.Match) -> str:
        token = f"\x00CODE{counter[0]}\x00"
        counter[0] += 1
        placeholders[token] = f"`{m.group(1)}`"
        return token

    return re.sub(r"`([^`]+)`", replace_inline, text)


def _restore_placeholders(text: str, placeholders: dict) -> str:
    for token, value in placeholders.items():
        text = text.replace(token, value)
    return text


# ---------------------------------------------------------------------------
# Conversão principal
# ---------------------------------------------------------------------------

def markdown_to_whatsapp(text: str) -> str:
    """
    Converte uma string com formatação Markdown para a formatação
    compatível com WhatsApp.

    Args:
        text: Texto de entrada em Markdown.

    Returns:
        Texto formatado para WhatsApp.
    """
    if not text:
        return text

    # 1. Protege blocos de código para não serem alterados
    text, placeholders = _protect_code_blocks(text)
    text = _protect_inline_code(text, placeholders)

    # 2. Imagens: ![alt](url) → remove completamente
    text = re.sub(r"!\[([^\]]*)\]\([^\)]*\)", "", text)

    # 3. Links: [texto](url) → "texto (url)"
    text = re.sub(r"\[([^\]]+)\]\(([^\)]+)\)", r"\1 (\2)", text)

    # 4. Cabeçalhos: # Título → *Título*
    #    Trata até nível 6, todos viram negrito
    text = re.sub(r"^#{1,6}\s+(.+)$", r"*\1*", text, flags=re.MULTILINE)

    # 5. Negrito + Itálico: ***texto*** → *_texto_*
    text = re.sub(r"\*{3}(.+?)\*{3}", r"*_\1_*", text, flags=re.DOTALL)

    # 6. Negrito: **texto** → *texto*
    text = re.sub(r"\*{2}(.+?)\*{2}", r"*\1*", text, flags=re.DOTALL)

    # 7. Itálico com asterisco: *texto* → _texto_
    #    (cuidado: não pode bater no negrito já convertido)
    #    Usa lookbehind/lookahead para não casar asteriscos sozinhos
    text = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"_\1_", text, flags=re.DOTALL)

    # 8. Tachado: ~~texto~~ → ~texto~
    text = re.sub(r"~~(.+?)~~", r"~\1~", text, flags=re.DOTALL)

    # 9. Citações (blockquote): > texto → ❝ texto
    text = re.sub(r"^>\s?(.+)$", r"❝ \1", text, flags=re.MULTILINE)

    # 10. Listas não ordenadas: - item ou * item (no início da linha) → • item
    text = re.sub(r"^[ \t]*[-*]\s+", "• ", text, flags=re.MULTILINE)

    # 11. Linhas horizontais: --- / *** / ___ → linha em branco
    text = re.sub(r"^(\*{3,}|-{3,}|_{3,})\s*$", "", text, flags=re.MULTILINE)

    # 12. Tabelas: converte para texto simples removendo separadores de pipe
    #     Linha de separador (|---|---| etc.) → removida
    text = re.sub(r"^\|[-| :]+\|$", "", text, flags=re.MULTILINE)
    #     Linhas de dados da tabela: | col1 | col2 | → "col1  col2"
    text = re.sub(
        r"^\|(.+)\|$",
        lambda m: "  ".join(c.strip() for c in m.group(1).split("|")),
        text,
        flags=re.MULTILINE,
    )

    # 13. Remove linhas em branco excessivas (mais de 2 consecutivas → 2)
    text = re.sub(r"\n{3,}", "\n\n", text)

    # 14. Restaura os blocos de código protegidos
    text = _restore_placeholders(text, placeholders)

    return text.strip()


def split_for_whatsapp(text: str, max_chars: int = 1200) -> list:
    """
    Divide uma resposta longa em várias mensagens de WhatsApp de forma semântica
    e humanizada, sem cortar no meio de palavras ou parágrafos.

    Estratégia (em ordem de prioridade):
    1. Divide no parágrafo duplo (\n\n) — fronteira natural entre ideias.
    2. Agrupa parágrafos curtos adjacentes até atingir max_chars.
    3. Se um parágrafo individual ainda exceder max_chars, divide nas linhas (\n).
    4. Se uma linha exceder max_chars, divide em fronteiras de frase (. ! ?).

    Args:
        text:      Texto já convertido para formato WhatsApp.
        max_chars: Tamanho máximo desejado por mensagem (padrão 1200).

    Returns:
        Lista de strings, cada uma sendo uma mensagem separada.
    """
    if not text:
        return []

    # ── 1. Divide em parágrafos (blocos separados por linha em branco) ──────
    paragraphs = [p.strip() for p in text.split("\n\n") if p.strip()]

    # ── 2. Agrupa parágrafos adjacentes até max_chars ───────────────────────
    groups: list[str] = []
    current = ""

    for para in paragraphs:
        if not current:
            current = para
        elif len(current) + 2 + len(para) <= max_chars:
            current += "\n\n" + para
        else:
            groups.append(current)
            current = para

    if current:
        groups.append(current)

    # ── 3. Quebra grupos ainda grandes demais nas linhas (\n) ───────────────
    result: list[str] = []

    for group in groups:
        if len(group) <= max_chars:
            result.append(group)
            continue

        lines = group.split("\n")
        chunk = ""
        for line in lines:
            if not chunk:
                chunk = line
            elif len(chunk) + 1 + len(line) <= max_chars:
                chunk += "\n" + line
            else:
                # ── 4. Se uma linha isolada ainda for grande, divide em frases
                if len(chunk) > max_chars:
                    result.extend(_split_by_sentence(chunk, max_chars))
                else:
                    result.append(chunk)
                chunk = line

        if chunk:
            if len(chunk) > max_chars:
                result.extend(_split_by_sentence(chunk, max_chars))
            else:
                result.append(chunk)

    return [r.strip() for r in result if r.strip()]


def _split_by_sentence(text: str, max_chars: int) -> list:
    """Divide um texto longo em fronteiras de frase como último recurso."""
    import re

    # Separa em sentenças preservando o delimitador
    sentences = re.split(r"(?<=[.!?])\s+", text)
    chunks: list[str] = []
    current = ""

    for sentence in sentences:
        if not current:
            current = sentence
        elif len(current) + 1 + len(sentence) <= max_chars:
            current += " " + sentence
        else:
            chunks.append(current)
            current = sentence

    if current:
        chunks.append(current)

    return chunks

