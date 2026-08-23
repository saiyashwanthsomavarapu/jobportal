<!DOCTYPE html>
<html lang="en" data-theme="accelon">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Job Not Found — Accelon Consulting</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">

    <?php include __DIR__ . "/theme.php"; ?>

    <style>
        body {
            font-family: var(--font-sans, "DM Sans", sans-serif);
        }

        .font-head {
            font-family: var(--font-head, "Syne", sans-serif);
        }
    </style>
</head>

<body class="min-h-screen bg-bg text-base-content">

    <!-- Header -->
    <div class="navbar bg-base-100 border-b border-base-300 px-4 lg:px-8">
        <div class="navbar-start">
            <a href="https://accelonconsulting.com">
                <img src="https://www.accelonconsulting.com/wp-content/uploads/2025/07/Accelon-logo.webp"
                    class="h-7 w-auto" alt="Accelon Consulting">
            </a>
        </div>

        <div class="navbar-end">
            <a href="https://accelonconsulting.com/careers" class="btn btn-ghost btn-sm text-base-content/70">
                View all roles
            </a>
        </div>
    </div>

    <!-- 404 Content -->
    <main class="min-h-[calc(100vh-73px)] flex items-center justify-center px-4 py-12">

        <div style="width: 100%; max-width: 480px;">

            <div style="border: 1px solid #e5e5e5; border-radius: 1rem; background: #ffffff; box-shadow: 0 1px 2px rgba(17, 24, 39, .04), 0 8px 24px -12px rgba(17, 24, 39, .08); padding: 2.5rem; text-align: center;">

                <!-- Icon -->
                <div style="display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 1rem; background: rgba(61, 107, 168, 0.1); color: #3d6ba8; margin: 0 auto 1.25rem;">
                    <svg style="width: 32px; height: 32px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <!-- Error code -->
                <div style="font-family: 'Syne', sans-serif; font-size: 4rem; font-weight: 800; letter-spacing: -0.05em; color: rgba(61, 107, 168, 0.15); line-height: 1;">
                    404
                </div>

                <h1 style="font-family: 'Syne', sans-serif; margin-top: 0.5rem; font-size: 1.75rem; font-weight: 700; color: #111827;">
                    Job not found
                </h1>

                <p style="margin-top: 0.75rem; max-width: 28rem; font-size: 0.9375rem; line-height: 1.6; color: #525252; margin-left: auto; margin-right: auto;">
                    The job you're looking for may have been removed, closed,
                    or the link may no longer be valid.
                </p>

                <!-- Actions -->
                <div style="margin-top: 1.75rem; display: flex; flex-direction: column; gap: 0.75rem; width: 100%;">

                    <a href="https://accelonconsulting.com/careers" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #3d6ba8; color: #ffffff; padding: 12px 24px; border-radius: 0.75rem; text-decoration: none; font-size: 14px; font-weight: 600; box-shadow: 0 4px 14px rgba(61, 107, 168, 0.22); transition: all 0.2s;">
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 12l6-6M3 12l6 6" />
                        </svg>
                        View All Jobs
                    </a>

                    <button type="button" onclick="history.back()" style="background: transparent; border: 1px solid #e5e5e5; color: #525252; padding: 12px 24px; border-radius: 0.75rem; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Go Back
                    </button>

                </div>

            </div>

            <p style="margin-top: 1.25rem; text-align: center; font-size: 12px; color: #9ca3af;">
                Accelon Consulting · Careers
            </p>

        </div>

    </main>

</body>

</html>