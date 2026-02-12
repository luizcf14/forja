import os
import sys
from agno.models.ollama import Ollama
from agno.models.google import Gemini
from .config import get_model_config

def get_model(model_type: str):
    """Factory to get the appropriate model instance based on configuration."""
    config = get_model_config(model_type)
    provider = config['provider']
    model_id = config['model_id']
    host = config['host']
    
    print(f"DEBUG: Loading {model_type} model: {provider}/{model_id}")

    if provider == 'ollama':
        return Ollama(id=model_id, host=host)
    elif provider == 'gemini':
         return Gemini(id=model_id)
    else:
        print(f"Warning: Unknown provider '{provider}', defaulting to Gemini")
        return Gemini(id=model_id)
