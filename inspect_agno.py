import agno
print("agno attributes:", dir(agno))
try:
    import agno.agent
    print("agno.agent attributes:", dir(agno.agent))
except ImportError:
    print("Could not import agno.agent")

try:
    import agno.storage
    print("agno.storage attributes:", dir(agno.storage))
except ImportError:
    print("Could not import agno.storage")
