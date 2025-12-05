try:
    from agno.storage.agent.sqlite import SqliteAgentStorage
    print("Import successful")
    storage = SqliteAgentStorage(table_name="test_agent_sessions", db_file="test_storage.db")
    print("Storage initialized")
except ImportError as e:
    print(f"ImportError: {e}")
except Exception as e:
    print(f"Error: {e}")
