<html>
<body>
<div class="content">
    <link rel="stylesheet" href='/static/style.css'>
    <div class="logo">
        <img src="/static/images/logo.png">
    </div>
    <div class="auth-resp">
        <h3><b>Auth Response:</b></h3>
    </div>
    <div class="success">
        <pre class="auth-token"><code class="auth-token" id="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></code></pre>
    </div>
</div>
</body>
</html>