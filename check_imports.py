try:
    from agno.knowledge.pdf import PDFKnowledgeBase
    print("PDFKnowledgeBase found")
except ImportError as e:
    print(f"PDFKnowledgeBase not found: {e}")

try:
    from agno.knowledge.reader.pdf_reader import PDFReader
    print("PDFReader found")
except ImportError as e:
    print(f"PDFReader not found: {e}")
