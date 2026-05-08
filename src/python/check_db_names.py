import os
import sys

# Setup environment to mimic main
from core.config import VECTOR_DB_PATH, PROJECT_ROOT
from agno.vectordb.lancedb import LanceDb, SearchType

# Let's check table kb_agent_1 or whatever exists in lancedb_data
db_path = VECTOR_DB_PATH
print(f"DB path: {db_path}")

# Iterate over files in lancedb_data to find a table
table_name = None
if os.path.exists(db_path):
    for f in os.listdir(db_path):
        if f.endswith('.lance'):
            table_name = f.replace('.lance', '')
            break

if table_name:
    print(f"Found table: {table_name}")
    vector_db = LanceDb(
        table_name=table_name,
        uri=str(db_path),
        search_type=SearchType.hybrid,
    )
    
    conn = vector_db.client
    # Let's list what names are in the table
    try:
        table = conn.open_table(table_name)
        df = table.to_pandas()
        if 'name' in df.columns:
            print("Unique 'name's in db:")
            print(df['name'].unique())
    except Exception as e:
        print("Error reading table:", e)
else:
    print("No lance table found")

