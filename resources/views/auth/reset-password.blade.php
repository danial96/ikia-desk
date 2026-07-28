<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — IKIA Desk</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap">
    <style>
        body { margin: 0; font-family: 'Open Sans', 'Segoe UI', ui-sans-serif, system-ui, sans-serif; }
        .ikia-gradient-bg { background: linear-gradient(145deg, #0d1b2a 0%, #0f2744 40%, #1a1060 100%); }
        .ikia-card { background: rgba(255,255,255,0.04); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.09); border-radius: 18px; }
        .ikia-input { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); color: white; border-radius: 10px; padding: 12px 16px; width: 100%; font-size: 14px; outline: none; transition: border-color .2s, background .2s; box-sizing: border-box; }
        .ikia-input::placeholder { color: rgba(255,255,255,.35); }
        .ikia-input:focus { border-color: #00D4E8; background: rgba(0,212,232,.07); }
        .ikia-btn { background: linear-gradient(135deg, #00C4D8 0%, #1B72E8 100%); color: white; border: none; border-radius: 10px; padding: 13px; width: 100%; font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity .2s, box-shadow .2s; letter-spacing: .02em; }
        .ikia-btn:hover { opacity: .92; box-shadow: 0 6px 20px rgba(27,114,232,.45); }
        label { display: block; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,.55); margin-bottom: 7px; }
        .form-group { margin-bottom: 16px; }
        .error-box { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; border-radius: 9px; padding: 11px 14px; font-size: 13px; margin-bottom: 16px; }
        .hint { font-size: 12px; color: rgba(255,255,255,.3); margin-top: 6px; }
    </style>
</head>
<body class="ikia-gradient-bg" style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">

    <div style="position:fixed;top:-120px;right:-120px;width:400px;height:400px;background:radial-gradient(circle,rgba(0,212,232,.18) 0%,transparent 70%);pointer-events:none;"></div>
    <div style="position:fixed;bottom:-100px;left:-100px;width:350px;height:350px;background:radial-gradient(circle,rgba(123,47,190,.2) 0%,transparent 70%);pointer-events:none;"></div>

    <div style="width:100%;max-width:400px;position:relative;">

        <div style="text-align:center;margin-bottom:32px;display:flex;flex-direction:column;align-items:center;">
            <img src="{{ asset('logo-white.png') }}" alt="IKIA Tech" style="height:64px;object-fit:contain;margin-bottom:22px;">
            <h1 style="color:white;font-size:22px;font-weight:700;margin:0 0 6px;">Set New Password</h1>
            <p style="color:rgba(255,255,255,.4);font-size:14px;margin:0;">Choose a strong password for your account</p>
        </div>

        <div class="ikia-card" style="padding:32px;">

            @if($errors->any())
            <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required
                           class="ikia-input" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label>NEW PASSWORD</label>
                    <input type="password" name="password" required class="ikia-input" placeholder="••••••••" autofocus>
                    <p class="hint">Minimum 8 characters</p>
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label>CONFIRM PASSWORD</label>
                    <input type="password" name="password_confirmation" required class="ikia-input" placeholder="••••••••">
                </div>
                <button type="submit" class="ikia-btn">Reset Password →</button>
            </form>

            <div style="text-align:center;margin-top:20px;">
                <a href="{{ route('login') }}" style="color:rgba(255,255,255,.4);font-size:13px;text-decoration:none;transition:color .2s;"
                   onmouseover="this.style.color='rgba(255,255,255,.75)'" onmouseout="this.style.color='rgba(255,255,255,.4)'">
                    ← Back to Sign In
                </a>
            </div>
        </div>

        <p style="text-align:center;color:rgba(255,255,255,.2);font-size:12px;margin-top:20px;">
            IKIA TECH &copy; {{ date('Y') }} — IKIA Desk
        </p>
    </div>
</body>
</html>
