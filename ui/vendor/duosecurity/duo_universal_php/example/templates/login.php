<html>
<body>
<div class="content">
    <link rel="stylesheet" href='/static/style.css'>
    <div class="logo">
        <img src="/static/images/logo.png">
    </div>
    <div class="output">
        <pre class="language-json"><code class="language-json" id="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></code></pre>
    </div>
    <form class="input-form" action="/" method="POST">
        <div class="form-group">
            <label for="exampleInputEmail1"><b>Name:</b></label>
            <input class="form-control" name="username" type="text" placeholder="username" id="exampleInputEmail1">
        </div>
        <div class="form-group">
            <label for="exampleInputPassword1"><b>Password:</b></label>
            <input class="form-control" name="password" type="password" placeholder="password" id="exampleInputPassword1">
        </div>
        <div class="buttons">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
    </form>
</div>
</body>
</html>