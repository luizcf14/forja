import inspect
from agno.vectordb.lancedb import LanceDb
from agno.knowledge.knowledge import Knowledge

print("LanceDB name_exists signature:")
print(inspect.signature(LanceDb.name_exists))

print("Knowledge document exists method?")
# Let's just check the signature of add_content
print(inspect.signature(Knowledge.add_content))
