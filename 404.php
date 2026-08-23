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

        <div class="w-full max-w-lg">

            <div class="card border border-base-300 bg-base-100 shadow-lg">
                <div class="card-body items-center text-center p-8 sm:p-10">

                    <!-- Icon -->
                    <div class="flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-5">
                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <!-- Error code -->
                    <div class="font-head text-5xl sm:text-6xl font-extrabold tracking-tight text-primary/15">
                        404
                    </div>

                    <h1 class="font-head mt-2 text-2xl sm:text-3xl font-bold text-base-content">
                        Job not found
                    </h1>

                    <p class="mt-3 max-w-md text-sm sm:text-base leading-6 text-base-content/60">
                        The job you're looking for may have been removed, closed,
                        or the link may no longer be valid.
                    </p>

                    <!-- Actions -->
                    <div class="mt-7 flex flex-col sm:flex-row gap-3 w-full sm:w-auto">

                        <a href="https://accelonconsulting.com/careers" class="btn btn-primary px-6">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 12l6-6M3 12l6 6" />
                            </svg>
                            View All Jobs
                        </a>

                        <button type="button" onclick="history.back()" class="btn btn-outline px-6">
                            Go Back
                        </button>

                    </div>

                </div>
            </div>

            <p class="mt-5 text-center text-xs text-base-content/40">
                Accelon Consulting · Careers
            </p>

        </div>

    </main>

</body>

</html>