
import pathlib

file_path = pathlib.Path(r"c:\Users\Solved-Blerys-Win\Documents\Povos\Forja\forja\src\python\parente.py")
content = file_path.read_text("utf-8")

target_line = 'self.log_message(session_id, "user", user_message)'
replacement = 'self.log_message(session_id, "user", user_message, media_type=kwargs.get("media_type"), media_url=kwargs.get("media_url"))'

if target_line in content:
    new_content = content.replace(target_line, replacement)
    file_path.write_text(new_content, "utf-8")
    print("Successfully patched parente.py")
else:
    print("Target line not found in parente.py")
    # check for variations?
    # maybe spacing?
    # print snippet around?
    lines = content.splitlines()
    for i, line in enumerate(lines):
        if 'self.log_message(session_id, "user", user_message)' in line:
            print(f"Found match loop at line {i+1}: {line}")
            # we can replace it here?
            # but simple string replace failed? means exact char match failed?
            pass
