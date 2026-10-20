import subprocess
code='''$_SERVER['SCRIPT_NAME']='/super-admin.php';$_SERVER['REQUEST_METHOD']='POST';$_GET=['page'=>'daily_work_status','search'=>'<test>'];require 'includes/workspace-unavailable.php';workspace_unavailable(new PDOException('SECRET DATABASE PASSWORD'));'''
r=subprocess.run(['php','-r',code],capture_output=True,text=True,check=True)
assert 'Workspace temporarily unavailable' in r.stdout
assert 'SECRET' not in r.stdout+r.stderr
assert '<form' not in r.stdout and 'method="post"' not in r.stdout
assert 'super-admin.php?page=daily_work_status&amp;search=%3Ctest%3E' in r.stdout
assert 'reference=' in r.stderr
print('PASS: database failure gives a safe GET recovery link, no automatic replay or leaked database details.')
