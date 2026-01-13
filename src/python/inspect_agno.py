import inspect
from agno.team import Team

print("Team.run signature:")
try:
    print(inspect.signature(Team.run))
except Exception as e:
    print(e)
