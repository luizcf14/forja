from agno.knowledge.embedder.google import GeminiEmbedder
import inspect

sig = inspect.signature(GeminiEmbedder.__init__)
for name, param in sig.parameters.items():
    print(f"{name}: {param.default}")
