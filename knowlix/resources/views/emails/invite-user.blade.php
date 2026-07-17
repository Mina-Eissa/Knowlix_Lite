<h2>You've been invited</h2>
<p>Hi {{ $user->name }}, you've been invited to join {{ $user->workspace->name }} as {{ $user->role->value }}.</p>
<p><a href="{{ $acceptUrl }}">Accept invitation and set your password</a></p>
<p>This link expires in 24 hours.</p>
