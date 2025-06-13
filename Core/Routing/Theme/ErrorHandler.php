<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>🤨 <?php echo htmlspecialchars($error['message'], ENT_COMPAT, 'UTF-8', false); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        body {
            background: #eeeef5;
            color: #68788c;
            font-family: system-ui, sans-serif;
            padding: 2rem;
        }
        .error {
            background: #DC143C;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            padding: 10px;
        }
        .container {
            background: #fff;
            border: 1px solid #e8e5ef;
            margin-top: 1rem;
        }
        .card-details {
            padding: 2rem 3rem;
            font-size: 1.2rem;
        }
        .error-class {
            opacity: 0.75;
            padding-bottom: 4px;
        }
        .error-message {
            color: black;
            font-weight: 600;
            line-height: 1.25;
            word-wrap: break-word;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 5;
            overflow: hidden;
            padding-bottom: 12px;
        }
        .link-site {
            font-size: 0.875rem;
            text-decoration: underline;
            color: rgba(30, 20, 70, 0.5);
        }
        .main {
            display: flex;
            margin-top: 1rem;
        }
        .load-files {
            background: #fff;
            width: 280px;
            height: 350px;
            overflow-y: auto;
        }
        .file-list {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.5rem;
            font-size: 12px;
        }
        .file-list .number {
            color: rgb(121, 0, 245);
            font-variant-numeric: tabular-nums;
            padding-right: 1rem;
        }
        .files {
            flex: 1;
            color: rgb(75, 71, 109);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .code {
            background: #fff;
            flex: 1;
            overflow: auto;
            height: 350px;
        }
        .highlighted {
            background: rgba(255, 3, 49, 0.37);
            font-weight: bold;
        }
        pre {
            font-family: monospace;
            font-size: 12px;
            margin: 0;
            padding: 0 1rem;
            white-space: pre-wrap;
        }
        .line {
            color: #1e144680;
            user-select: none;
            display: inline-block;
            min-width: 3em;
            text-align: right;
            margin-right: 1em;
        }
    </style>
</head>
<body>
<div class="error">
    <?php echo 'Type: ' . $error['type'] . ', '; ?>
    <?php if (isset($error['code'])): ?>
        Code: <span style="color:#e1e1e1">[<?php echo $error['code']; ?>]</span>
    <?php endif; ?>
</div>
<div class="container">
    <div class="card-details">
        <div class="error-class">
            <?php echo $error['file']; ?>
        </div>
        <div class="error-message">
            <?php echo htmlspecialchars($error['message'], ENT_COMPAT, 'UTF-8', false); ?>
        </div>
        <a href="/" class="link-site">http://127.0.0.1:8000/</a>
    </div>
</div>
<div class="main">
    <div class="load-files">
        <ul>
            <?php foreach (get_included_files() as $k => $v): ?>
                <li class="file-list">
                    <div class="number"><?php echo $k + 1; ?></div>
                    <div class="files"><?php echo $v; ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="code">
        <?php if (!empty($error['highlighted'])): ?>
            <?php foreach ($error['highlighted'] as $line): ?>
                <pre<?php if ($line['highlighted']): ?> class="highlighted"<?php endif; ?>>
<span class="line"><?php echo $line['number']; ?></span> <?php echo $line['code']; ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
