"""
Serviço de conformidade com a LGPD (Lei Geral de Proteção de Dados - Lei nº 13.709/2018).

Responsável por:
- Verificar se um número de telefone já aceitou a política de privacidade
- Detectar se a mensagem do usuário é uma resposta de aceite/recusa
- Gerar o texto da política para envio via WhatsApp
- Registrar o consentimento no banco de dados
"""

import unicodedata
import re
from typing import Optional

from core.repositories import ConversationRepository


# ---------------------------------------------------------------------------
# Palavras que indicam ACEITE
# ---------------------------------------------------------------------------
_ACCEPTED_WORDS = {
    "sim", "s", "aceito", "aceitar", "aceitei", "aceita",
    "ok", "okay", "ok!", "sim!",
    "yes", "y",
    "concordo", "concordei",
    "claro", "claro que sim", "claro que sí",
    "com certeza", "certeza", "certo", "certa",
    "com prazer", "prazer",
    "ótimo", "otimo", "ótima", "otima",
    "top", "tudo bem", "tá bom", "ta bom", "tá", "ta",
    "pode", "pode sim", "pode ser", "vai", "vamos",
    "quero", "quero sim",
    "beleza", "show", "perfeito",
    "afirmativo", "positivo",
    "combinado", "combinado!",
}

# ---------------------------------------------------------------------------
# Palavras que indicam RECUSA
# ---------------------------------------------------------------------------
_REJECTED_WORDS = {
    "não", "nao", "n", "não quero", "nao quero",
    "no", "nope",
    "recuso", "recusar", "recusei", "rejeito",
    "discordo",
    "negativo", "não aceito", "nao aceito",
    "não concordo", "nao concordo",
}


def _normalize(text: str) -> str:
    """Remove acentos, pontuação e converte para minúsculas para comparação robusta."""
    # Remove acentos
    normalized = unicodedata.normalize("NFD", text)
    normalized = "".join(c for c in normalized if unicodedata.category(c) != "Mn")
    # Lowercase
    normalized = normalized.lower().strip()
    # Remove pontuação exceto espaços
    normalized = re.sub(r"[^\w\s]", "", normalized)
    # Colapsa espaços múltiplos
    normalized = re.sub(r"\s+", " ", normalized).strip()
    return normalized


# Conjuntos normalizados — construídos uma única vez no import
_ACCEPTED_NORMALIZED = {_normalize(w) for w in _ACCEPTED_WORDS}
_REJECTED_NORMALIZED = {_normalize(w) for w in _REJECTED_WORDS}

# ---------------------------------------------------------------------------
# Textos das mensagens
# ---------------------------------------------------------------------------
_POLICY_MSG_1 = (
    "📋 *Política de Privacidade e Uso de Dados*\n\n"
    "Olá, parente! 👋 Antes de começarmos, preciso te contar como cuidamos dos seus dados.\n\n"
    "A *Rede Conexão Povos da Floresta* coleta e usa as mensagens enviadas neste chat para:\n"
    "• ✅ Responder suas dúvidas com a ajuda da IA Parente\n"
    "• ✅ Melhorar o serviço prestado à Rede\n"
    "• ✅ Registrar o histórico de atendimento\n\n"
    "Seus dados são tratados em conformidade com a *LGPD (Lei nº 13.709/2018)*.\n\n"
    "Não compartilhamos seus dados com terceiros sem seu consentimento, exceto quando exigido por lei.\n\n"
    "📖 Leia nossa política completa:\n"
    "https://conexaopovosdafloresta.org.br/politica-de-privacidade-e-seguranca-de-dados/"
)

_POLICY_MSG_2 = (
    "Para continuar, preciso do seu consentimento. 🙏\n\n"
    "✅ Responda *SIM* para aceitar e continuar usando o Parente.\n"
    "❌ Responda *NÃO* se preferir não continuar.\n\n"
    "Dúvidas ou solicitação de exclusão de dados?\n"
    "Entre em contato pelo WhatsApp: *+55 (97) 8459-0059*"
)

_CONSENT_ACCEPTED_MSG = (
    "✅ *Ótimo, parente!* Seu consentimento foi registrado.\n\n"
    "Agora podemos conversar normalmente. Como posso te ajudar hoje? 😊"
)

_CONSENT_REJECTED_MSG = (
    "Entendemos, parente. 🙏\n\n"
    "Sem o aceite da política de privacidade não é possível utilizar o serviço da IA Parente.\n\n"
    "Se mudar de ideia, é só nos mandar uma mensagem. Estamos aqui! 💚"
)

_CONSENT_INVALID_MSG = (
    "Não entendi sua resposta, parente. 😅\n\n"
    "Por favor, responda:\n"
    "✅ *SIM* para aceitar\n"
    "❌ *NÃO* para recusar"
)

_ALREADY_REJECTED_MSG = (
    "Você optou por não aceitar nossa Política de Privacidade. 🙏\n\n"
    "Por isso não consigo te ajudar por aqui.\n\n"
    "Se quiser rever sua decisão, entre em contato pelo WhatsApp: *+55 (97) 8459-0059*"
)

_RECONSIDERATION_MSG = (
    "Que bom que voltou, parente! 😊\n\n"
    "Antes de continuar, vou te apresentar novamente nossa Política de Privacidade "
    "para que você possa decidir com calma."
)


class LGPDService:
    """Serviço de conformidade LGPD para o fluxo de consentimento via WhatsApp."""

    # ------------------------------------------------------------------
    # Consultas ao banco
    # ------------------------------------------------------------------

    @staticmethod
    def get_consent_status(phone_number: str) -> dict:
        """
        Retorna o estado LGPD de um usuário.

        Returns:
            dict com chaves:
                - status: 'pending' | 'accepted' | 'rejected'
                - awaiting_response: bool
        """
        return ConversationRepository.get_lgpd_status(phone_number)

    @staticmethod
    def needs_consent(phone_number: str) -> bool:
        """True se o usuário ainda não aceitou a política."""
        info = LGPDService.get_consent_status(phone_number)
        return info["status"] != "accepted"

    @staticmethod
    def is_waiting_response(phone_number: str) -> bool:
        """True se a política já foi enviada e aguardamos a resposta."""
        info = LGPDService.get_consent_status(phone_number)
        return bool(info.get("awaiting_response"))

    # ------------------------------------------------------------------
    # Parsing da resposta do usuário
    # ------------------------------------------------------------------

    @staticmethod
    def parse_consent_response(message_text: str) -> Optional[str]:
        """
        Analisa a mensagem e retorna:
            - 'accepted'  se o usuário aceita
            - 'rejected'  se o usuário recusa
            - None        se não é uma resposta reconhecível de consentimento
        """
        normalized = _normalize(message_text)

        if normalized in _ACCEPTED_NORMALIZED:
            return "accepted"

        if normalized in _REJECTED_NORMALIZED:
            return "rejected"

        return None

    # ------------------------------------------------------------------
    # Registro no banco
    # ------------------------------------------------------------------

    @staticmethod
    def record_consent(phone_number: str, status: str):
        """Grava o aceite ('accepted') ou recusa ('rejected') com timestamp."""
        ConversationRepository.set_lgpd_consent(phone_number, status)

    @staticmethod
    def mark_awaiting(phone_number: str, waiting: bool = True):
        """Marca/desmarca o flag de 'aguardando resposta' para o número."""
        ConversationRepository.set_lgpd_awaiting(phone_number, waiting)

    # ------------------------------------------------------------------
    # Geração de mensagens
    # ------------------------------------------------------------------

    @staticmethod
    def get_policy_messages() -> list:
        """Retorna a lista de mensagens da política prontas para envio (em ordem)."""
        return [_POLICY_MSG_1, _POLICY_MSG_2]

    @staticmethod
    def get_consent_accepted_message() -> str:
        return _CONSENT_ACCEPTED_MSG

    @staticmethod
    def get_consent_rejected_message() -> str:
        return _CONSENT_REJECTED_MSG

    @staticmethod
    def get_consent_invalid_message() -> str:
        return _CONSENT_INVALID_MSG

    @staticmethod
    def get_reconsideration_message() -> str:
        """Mensagem enviada quando um usuário que recusou manda nova mensagem,
        sinalizando que quer reconsiderar o aceite."""
        return _RECONSIDERATION_MSG
