import os
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Base Paths
# This file is in src/python/core/config.py
# SCRIPT_DIR is src/python/core
CRIPT_DIR = Path(__file__).parent.absolute()
# PROJECT_ROOT is src/python/../../.. = root
PROJECT_ROOT = CRIPT_DIR.parent.parent.parent

# Database
DB_PATH = PROJECT_ROOT / "database.sqlite"
VECTOR_DB_PATH = PROJECT_ROOT / "lancedb_data"

# Keys
WHATSAPP_VERIFY_TOKEN = os.getenv("WHATSAPP_VERIFY_TOKEN")

# Agent Defaults
DEFAULT_MODEL_PROVIDER = "gemini"
DEFAULT_MODEL_NAME = "gemini-2.5-flash"
DEFAULT_HOST = "http://localhost:11434"

def get_model_config(model_type: str = 'slow') -> dict:
    """Returns model configuration for a specific type (fast/slow)."""
    model_type = model_type.lower() if model_type else 'slow'
    return {
        "provider": os.getenv(f"{model_type.upper()}_MODEL_PROVIDER", DEFAULT_MODEL_PROVIDER).lower(),
        "model_id": os.getenv(f"{model_type.upper()}_MODEL_NAME", DEFAULT_MODEL_NAME),
        "host": os.getenv(f"{model_type.upper()}_MODEL_HOST", DEFAULT_HOST)
    }
