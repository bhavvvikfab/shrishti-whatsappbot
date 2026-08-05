@extends('layouts.app')

@section('content')
    <div class="min-vh-100 d-flex align-items-center position-relative overflow-x-hidden py-4 py-md-0"
        style="background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);">

        <div class="position-absolute top-0 start-0 w-100 h-100" style="z-index: 1; opacity: 0.85;">
            <svg width="100%" height="100%" viewBox="0 0 1440 320" preserveAspectRatio="none"
                style="position: absolute; bottom: 0;">
                <!-- Dark Blue Wave Layer 1 -->
                <path fill="#1e3a8a" fill-opacity="0.75"
                    d="M0,192L48,197.3C96,203,192,213,288,213.3C384,213,480,203,576,186.7C672,171,768,149,864,154.7C960,160,1056,192,1152,197.3C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z">
                </path>

                <!-- Dark Blue Wave Layer 2 (slightly different shade) -->
                <path fill="#1e40af" fill-opacity="0.65"
                    d="M0,256L48,240C96,224,192,192,288,186.7C384,181,480,203,576,213.3C672,224,768,224,864,202.7C960,181,1056,139,1152,122.7C1248,107,1344,117,1392,122.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z">
                </path>
            </svg>
        </div>

        <div class="container position-relative" style="z-index: 2;">
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6 col-md-8">
                    <div class="card login-card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-body" style="padding: 2rem !important;">

                            <!-- Header -->
                            @php
                                $companyName = $companyName ?? config('shrishti_trip.name', 'Shrishti Trip');
                                $loginLogo = url(
                                    (env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') .
                                        'images/logo1.png',
                                );
                            @endphp
                            <div class="text-center">
                                <img src="{{ $loginLogo }}" alt="{{ $companyName }}" class="login-logo mb-3">
                                <p class="text-muted mb-0">
                                    Welcome to Fablead WA-BOT, Manage all your WhatsApp conversations in one place.
                                </p>
                            </div>

                            <div class="my-4">
                                <div class="d-flex align-items-center gap-2">
                                    <hr class="flex-grow-1 border-1 border-secondary">
                                    <span class="fs-3 px-1" style="color: #17234a">Login</span>
                                    <hr class="flex-grow-1 border-1 border-secondary">
                                </div>
                            </div>

                            <form id="loginForm" novalidate>
                                @csrf
                                <div id="loginAlert" class="alert alert-danger d-none" role="alert"></div>

                                <!-- Email -->
                                <div class="mb-4">
                                    <label for="email" class="form-label fw-medium">Email Address</label>
                                    <input id="email" type="email" class="form-control form-control-lg" name="email"
                                        required autofocus>
                                </div>

                                <!-- Password -->
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-medium">Password</label>
                                    <div class="position-relative">
                                        <input id="password" type="password"
                                            class="form-control form-control-lg has-toggle" name="password" required>
                                        <button type="button" class="password-toggle" id="togglePassword"
                                            aria-label="Show password">
                                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Login Button -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-lg fw-semibold text-white"
                                        style="background: linear-gradient(135deg, #2b3a69, #182244); border: none;">
                                        Sign In
                                    </button>
                                </div>
                            </form>

                           <footer class="mt-4 text-center text-muted" style="font-size: 0.8rem;">
                                    © 2026 Copyright - <a href="https://www.fableadtechnolabs.com/" target="_blank" style="text-decoration:none; color:inherit;">Fablead Developers Technolab</a>
                                </footer>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .card {
            border-radius: 20px !important;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .form-control:focus {
            border-color: #17234a;
            box-shadow: 0 0 0 4px rgba(27, 28, 77, 0.12);
        }

        .login-logo {
            max-width: 180px;
            height: auto;
            border-radius: 8px; /* Moved from inline style */
            transition: background-color 0.3s;
        }

        [data-theme="dark"] .login-logo {
            background-color: white;
            padding: 8px;
        }

        .form-control.has-toggle {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 4px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: #17234a;
        }

        /* Phones: keep the floating card, just tighten it up so it fits narrow screens. */
        @media (max-width: 575.98px) {
            /* Bootstrap's p-5 utility is !important, so this override must be too. */
            .login-card .card-body {
                padding: 1.5rem 1.25rem !important;
            }

            .login-logo {
                max-width: 100px;
                margin-bottom: 0.5rem !important;
            }
            
            .login-card .my-4 {
                margin-top: 1rem !important;
                margin-bottom: 1rem !important;
            }

            .login-card .mb-4 {
                margin-bottom: 1rem !important;
            }

            .login-card .mt-4 {
                margin-top: 1rem !important;
            }

            /* 16px stops iOS zooming the page when a field takes focus. */
            .login-card .form-control {
                font-size: 16px;
                padding: 10px 14px;
            }
        }
    </style>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Setup token interceptor for jQuery AJAX
        $(document).ready(function() {
            const tokenKey = 'crm_auth_token';
            
            // Set up jQuery AJAX beforeSend to add token to all requests
            $(document).ajaxSend(function(event, jqXHR, ajaxOptions) {
                const token = localStorage.getItem(tokenKey);
                if (token) {
                    jqXHR.setRequestHeader('Authorization', 'Bearer ' + token);
                }
            });
        });

        // Override fetch for any fetch requests (if needed)
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const token = localStorage.getItem('crm_auth_token');
            
            if (token) {
                if (args[1]) {
                    if (!args[1].headers) {
                        args[1].headers = {};
                    }
                    args[1].headers['Authorization'] = 'Bearer ' + token;
                } else {
                    args[1] = {
                        headers: {
                            'Authorization': 'Bearer ' + token
                        }
                    };
                }
            }
            return originalFetch.apply(this, args);
        };
    </script>
    
    <script>
    $(document).ready(function() {
        const form = $('#loginForm');
        const alertBox = $('#loginAlert');
        const submitButton = form.find('button[type="submit"]');
        const tokenKey = 'crm_auth_token';
        const userKey = 'crm_user';

        if (!form.length || !submitButton.length) {
            return;
        }

        $('#togglePassword').on('click', function() {
            const input = $('#password');
            const isHidden = input.attr('type') === 'password';
            input.attr('type', isHidden ? 'text' : 'password');
            $('#togglePasswordIcon').toggleClass('bi-eye', !isHidden).toggleClass('bi-eye-slash', isHidden);
            $(this).attr('aria-label', isHidden ? 'Hide password' : 'Show password');
        });

        form.on('submit', function(event) {
            event.preventDefault();

            const email = $('#email').val();
            const password = $('#password').val();

            alertBox.addClass('d-none');
            alertBox.text('');
            submitButton.prop('disabled', true);

            // Get CSRF token from meta tag or input
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || 
                             $('input[name="_token"]').val();

            // Direct route URL for login.post
            const loginUrl = '{{ route("login.post") }}';

            // AJAX login request
            $.ajax({
                url: loginUrl,
                method: 'POST',
                data: JSON.stringify({
                    email: email,
                    password: password,
                    _token: csrfToken,
                }),
                contentType: 'application/json',
                dataType: 'json',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                xhrFields: {
                    withCredentials: true
                },
                success: function(data, textStatus, jqXHR) {
                        if (data.success) {
                            // Store token in localStorage
                            if (data.token) {
                                localStorage.setItem(tokenKey, data.token);
                                localStorage.setItem(userKey, JSON.stringify(data.user));
                                console.log('Login successful. Token stored.', {
                                    userId: data.user?.id,
                                    userName: data.user?.name,
                                    userRoles: data.user?.roles,
                                });

                                // Redirect to whatsapp/inbox
                                window.location.href = '/whatsapp/inbox';
                            } else {
                                console.warn('Login successful but no token received');
                                // Still redirect - session auth should work
                                window.location.href = '/whatsapp/inbox';
                            }
                        } else {
                            console.error('Login failed:', data);
                            alertBox.text(data.message || 'Login failed. Please check your credentials.');
                            alertBox.removeClass('d-none');
                            submitButton.prop('disabled', false);
                        }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Login error:', textStatus, errorThrown);

                    // Try to parse error response
                    let errorMessage = 'An error occurred. Please try again.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    } else if (jqXHR.responseText) {
                        try {
                            const response = JSON.parse(jqXHR.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch(e) {
                            // If not JSON, use default message
                        }
                    }
                    
                    alertBox.text(errorMessage);
                    alertBox.removeClass('d-none');
                    submitButton.prop('disabled', false);
                }
            });
        });
    });
</script>
@endsection