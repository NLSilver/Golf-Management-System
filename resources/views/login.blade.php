<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - DEGMS | Davao EagleMasters Driving Range</title>
    @vite(['resources/css/bootstrap.css', 'resources/js/bootstrap.js'])
    <style>
        body {
            min-height: 100vh;
            background: #0b1f14;
        }

        .login-wrap {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
        }

        /* --- Left: brand / hero --------------------------------------- */
        .login-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }

        .login-hero img.hero-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 40%;
            filter: saturate(1.05);
        }

        .login-hero .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(20, 83, 45, .45) 0%, rgba(11, 31, 20, .88) 100%);
        }

        .login-hero .hero-copy {
            position: relative;
            padding: 3rem;
            color: #fff;
            max-width: 40rem;
            text-shadow: 0 1px 3px rgba(0, 0, 0, .45);
        }

        .login-hero .hero-copy h1 {
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .login-hero .hero-copy p {
            color: rgba(255, 255, 255, .82);
        }

        /* --- Right: form ------------------------------------------------ */
        .login-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f4f7f5;
        }

        .login-card {
            width: 100%;
            max-width: 26rem;
            background: #fff;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 18px 45px rgba(11, 31, 20, .14);
            padding: 2.25rem;
        }

        .login-card .form-control,
        .login-card .input-group-text {
            border-radius: .6rem;
        }

        .login-card .form-control:focus {
            border-color: #14532d;
            box-shadow: 0 0 0 .2rem rgba(20, 83, 45, .15);
        }

        .btn-login {
            background: #14532d;
            border-color: #14532d;
            font-weight: 600;
            border-radius: .6rem;
            padding: .65rem;
        }

        .btn-login:hover,
        .btn-login:focus {
            background: #166534;
            border-color: #166534;
        }

        .login-logo {
            height: 4.25rem;
            width: auto;
        }

        @media (max-width: 991.98px) {
            .login-wrap {
                grid-template-columns: 1fr;
            }

            .login-hero {
                min-height: 220px;
            }

            .login-hero .hero-copy {
                padding: 1.5rem;
            }

            .login-hero .hero-copy p {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="login-wrap">

    {{-- Brand panel --}}
    <section class="login-hero">
        <img class="hero-bg" src="{{ asset('image/driving-range.jpg') }}" alt="Davao EagleMasters driving range">
        <div class="hero-overlay"></div>
        <div class="hero-copy">
            <div class="small text-white-50 mb-3">Golf Driving Range &middot; Facility MIS</div>
            <h1 class="h2 mb-2">DEGMS Facility Management</h1>
            <p class="mb-3">
                Bay allocation, time-based billing, employee DTR and daily reports &mdash;
                all in one front-desk system.
            </p>
        </div>
    </section>

    {{-- Form panel --}}
    <section class="login-panel">
        <div class="login-card">
            <div class="text-center mb-4">
                <img class="login-logo mb-3"
                     src="{{ asset('image/Screenshot_2026-09-29_173316-removebg-preview.png') }}"
                     alt="DEGMS logo">
                <h2 class="h4 fw-bold mb-1">Welcome back</h2>
                <p class="text-muted small mb-0">Sign in to continue to the front-desk dashboard</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small mb-3" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.perform') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">&#128100;</span>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="e.g. admin" value="{{ old('username') }}"
                               autocomplete="username" autofocus required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">&#128274;</span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Your password" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-login">Sign in</button>
                </div>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                DEGMS &copy; {{ now()->year }} &middot; Davao EagleMasters Golf Driving Range
            </p>
        </div>
    </section>
</div>
</body>
</html>
