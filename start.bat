@echo off
echo Starting Agent Forge...
echo Open http://localhost:8000 in your browser.
source .venv/bin/activate
php -c php.ini -S localhost:8000 -t public
pause
