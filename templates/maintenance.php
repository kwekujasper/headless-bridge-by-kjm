<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php esc_html_e( 'Site Temporarily Unavailable', 'headless-bridge-by-kjm' ); ?></title>
	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			background: #0f0f1a;
			color: #e2e8f0;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 2rem;
		}

		.maintenance-card {
			background: #1e1e2e;
			border: 1px solid #2d2d4e;
			border-radius: 12px;
			padding: 3rem 2.5rem;
			max-width: 480px;
			width: 100%;
			text-align: center;
			box-shadow: 0 20px 60px rgba(0,0,0,0.4);
		}

		.maintenance-icon {
			font-size: 4rem;
			margin-bottom: 1.5rem;
			display: block;
		}

		h1 {
			font-size: 1.5rem;
			font-weight: 700;
			margin-bottom: 1rem;
			color: #f1f5f9;
		}

		p {
			color: #94a3b8;
			line-height: 1.7;
			margin-bottom: 0.75rem;
		}

		.badge {
			display: inline-block;
			margin-top: 2rem;
			padding: 0.25rem 0.75rem;
			background: #4f46e5;
			color: #fff;
			border-radius: 999px;
			font-size: 0.75rem;
			font-weight: 600;
			letter-spacing: 0.05em;
			text-transform: uppercase;
		}
	</style>
</head>
<body>
	<div class="maintenance-card">
		<span class="maintenance-icon" aria-hidden="true">⚡</span>
		<h1><?php esc_html_e( 'Frontend Temporarily Unavailable', 'headless-bridge-by-kjm' ); ?></h1>
		<p><?php esc_html_e( 'We\'re making improvements to bring you a better experience. We\'ll be back online very soon.', 'headless-bridge-by-kjm' ); ?></p>
		<p><?php esc_html_e( 'Thank you for your patience.', 'headless-bridge-by-kjm' ); ?></p>
		<span class="badge">Headless Bridge by KJM</span>
	</div>
</body>
</html>
