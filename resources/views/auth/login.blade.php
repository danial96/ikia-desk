<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IKIA Desk</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap">
    <style>
        body { margin: 0; font-family: 'Open Sans', 'Segoe UI', ui-sans-serif, system-ui, sans-serif; }
        .ikia-gradient-bg {
            background: linear-gradient(145deg, #0d1b2a 0%, #0f2744 40%, #1a1060 100%);
        }
        .ikia-card {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 18px;
        }
        .ikia-input {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: white;
            border-radius: 10px;
            padding: 12px 16px;
            width: 100%;
            font-size: 14px;
            outline: none;
            transition: border-color .2s, background .2s;
            box-sizing: border-box;
        }
        .ikia-input::placeholder { color: rgba(255,255,255,.35); }
        .ikia-input:focus { border-color: #00D4E8; background: rgba(0,212,232,.07); }
        .ikia-btn {
            background: linear-gradient(135deg, #00C4D8 0%, #1B72E8 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, box-shadow .2s;
            letter-spacing: .02em;
        }
        .ikia-btn:hover { opacity: .92; box-shadow: 0 6px 20px rgba(27,114,232,.45); }
        label { display: block; font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,.55); margin-bottom: 7px; }
        .form-group { margin-bottom: 16px; }
        .error-box {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.3);
            color: #fca5a5;
            border-radius: 9px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .checkbox-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; }
        .checkbox-row input { width:15px; height:15px; accent-color:#00D4E8; }
        .checkbox-row span { font-size:13px; color:rgba(255,255,255,.5); }
    </style>
</head>
<body class="ikia-gradient-bg" style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">

    {{-- Decorative blobs --}}
    <div style="position:fixed;top:-120px;right:-120px;width:400px;height:400px;background:radial-gradient(circle,rgba(0,212,232,.18) 0%,transparent 70%);pointer-events:none;"></div>
    <div style="position:fixed;bottom:-100px;left:-100px;width:350px;height:350px;background:radial-gradient(circle,rgba(123,47,190,.2) 0%,transparent 70%);pointer-events:none;"></div>

    <div style="width:100%;max-width:400px;position:relative;">

        {{-- Logo & heading --}}
        <div style="text-align:center;margin-bottom:32px;display:flex;flex-direction:column;align-items:center;">
            <img src="{{ asset('logo-white.png') }}" alt="IKIA Tech" style="height:64px;object-fit:contain;margin-bottom:22px;display:block;margin-left:auto;margin-right:auto;">
            <h1 style="color:white;font-size:22px;font-weight:700;margin:0 0 6px;">Welcome back</h1>
            <p style="color:rgba(255,255,255,.4);font-size:14px;margin:0;">Sign in to your IKIA Desk account</p>
        </div>

        <div class="ikia-card" style="padding:32px;">

            @if(session('status'))
            <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac;border-radius:9px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                <svg style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('status') }}
            </div>
            @endif
            @if($errors->any())
            <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>EMAIL ADDRESS</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="ikia-input" placeholder="your@email.com" autocomplete="email">
                </div>
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
                        <label style="margin-bottom:0;">PASSWORD</label>
                        <a href="{{ route('password.request') }}"
                           style="font-size:12px;color:rgba(0,212,232,.8);text-decoration:none;transition:color .2s;"
                           onmouseover="this.style.color='#00D4E8'" onmouseout="this.style.color='rgba(0,212,232,.8)'">
                            Forgot password?
                        </a>
                    </div>
                    <input type="password" name="password" required
                           class="ikia-input" placeholder="••••••••" autocomplete="current-password">
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Remember me for 30 days</span>
                </div>
                <button type="submit" class="ikia-btn">Sign In →</button>
            </form>
        </div>

        <p style="text-align:center;color:rgba(255,255,255,.2);font-size:12px;margin-top:20px;">
            IKIA TECH &copy; {{ date('Y') }} — IKIA Desk
        </p>
    </div>
</body>
</html>
