import inspect
from agno.knowledge.knowledge import Knowledge

sig = inspect.signature(Knowledge.__init__)
for name, param in sig.parameters.items():
    print(f"{name}: {param.default}")

print("\nHas max_results in dir?", 'max_results' in dir(Knowledge))
