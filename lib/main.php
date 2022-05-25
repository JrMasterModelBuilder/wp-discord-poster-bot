<?php

require_once __DIR__ . '/vars.php';

/**
 * The main plugin class.
 */
class WP_DISCORD_POSTER_BOT_MAIN {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = WP_DISCORD_POSTER_BOT_VERSION;

	/**
	 * Plugin entry.
	 *
	 * @var string
	 */
	const ENTRY = WP_DISCORD_POSTER_BOT_ENTRY;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	const SLUG = 'wp-discord-poster-bot';

	/**
	 * Init plugin.
	 */
	public static function init(): void {
		add_action('admin_menu', [static::class, 'action_admin_menu']);
		add_action('admin_init', [static::class, 'action_admin_init']);
		add_action(
			'wp_after_insert_post',
			[static::class, 'action_wp_after_insert_post'],
			10,
			4
		);
	}

	/**
	 * Get the plugin title.
	 *
	 * @return string Plugin title.
	 */
	public static function title(): string {
		return __('WP Discord Poster Bot', 'wp-discord-poster-bot');
	}

	/**
	 * Get options.
	 *
	 * @return array|null Options array or null if options not set.
	 */
	public static function options(): ?array {
		$r = get_option(static::SLUG);
		return is_array($r) ? $r : null;
	}

	/**
	 * Get option by name.
	 *
	 * @param string $name Option name.
	 * @return mixed option value or null if option not set.
	 */
	public static function option(string $name) {
		return static::options()[$name] ?? null;
	}

	/**
	 * Action for admin menu.
	 */
	public static function action_admin_menu(): void {
		add_options_page(
			static::title(),
			static::title(),
			'manage_options',
			static::SLUG,
			[static::class, 'options_page_content']
		);
	}

	/**
	 * Action for admin init.
	 */
	public static function action_admin_init(): void {
		register_setting(static::SLUG, static::SLUG);

		add_settings_section(
			static::SLUG . '-webhook',
			__('Webhook', 'wp-discord-poster-bot'),
			function () {
				echo '<p>',
					__('Webhook URL from Discord.', 'wp-discord-poster-bot'),
					'</p>';
			},
			static::SLUG
		);
		add_settings_field(
			'webhook',
			__('Webhook URL', 'wp-discord-poster-bot'),
			function () {
				echo '<input',
					' type="text"',
					' class="large-text code"',
					' name="' . esc_attr(static::SLUG) . '[webhook-url]"',
					' value="' . esc_attr(static::option('webhook-url')) . '"',
					'>';
			},
			static::SLUG,
			static::SLUG . '-webhook'
		);

		add_settings_section(
			static::SLUG . '-post-types',
			__('Post Types', 'wp-discord-poster-bot'),
			function () {
				echo '<p>',
					__(
						'The webhook URL from Discord.',
						'wp-discord-poster-bot'
					),
					'</p>';
			},
			static::SLUG
		);
		add_settings_field(
			'post-types',
			__('Post Types', 'wp-discord-poster-bot'),
			function () {
				$types = get_post_types(['public' => true], 'objects');
				$value = static::option('post-types');
				echo '<fieldset>';
				echo '<legend class="screen-reader-text">',
					__('Post Types', 'wp-discord-poster-bot'),
					'</legend>';
				foreach ($types as $t => $pt) {
					echo '<label>',
						'<input',
						' type="checkbox"',
						' name="' .
							esc_attr(static::SLUG) .
							'[post-types][' .
							esc_attr($t) .
							']"',
						' value="1"',
						empty($value[$t]) ? '' : ' checked',
						'>',
						$pt->label,
						'</label><br>';
				}
				echo '</fieldset>';
			},
			static::SLUG,
			static::SLUG . '-post-types'
		);

		add_settings_section(
			static::SLUG . '-format',
			__('Post Format', 'wp-discord-poster-bot'),
			function () {
				echo '<p>',
					__('Discord post format string.', 'wp-discord-poster-bot'),
					'</p>';
				echo '<p>';
				foreach (WP_DISCORD_POSTER_BOT_VARS::vars() as $var) {
					echo '<code>%' . esc_html($var) . '%</code>';
				}
				echo '</p>';
			},
			static::SLUG
		);
		add_settings_field(
			'format',
			__('Format String', 'wp-discord-poster-bot'),
			function () {
				echo '<textarea',
					' class="large-text code"',
					' name="' . esc_attr(static::SLUG) . '[format]"',
					' rows="10"',
					'>',
					esc_html(static::option('format') ?? ''),
					'</textarea>';
			},
			static::SLUG,
			static::SLUG . '-format'
		);
	}

	/**
	 * Callback for options page.
	 */
	public static function options_page_content(): void {
		echo '<div class="wrap">';
		echo '<form action="options.php" method="POST">';
		echo '<h1>', esc_html(static::title()), '</h1>';
		settings_fields(static::SLUG);
		do_settings_sections(static::SLUG);
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Callback for after a post and meta data is saved.
	 *
	 * @param int $postId Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool $update Flag for if updated.
	 * @param WP_Post|null $postBefore Old post if exists.
	 * @return void
	 */
	public static function action_wp_after_insert_post(
		int $postId,
		WP_Post $post,
		bool $update,
		?WP_Post $postBefore
	): void {
		$newStatus = $post->post_status;
		if ($newStatus != 'publish') {
			return;
		}

		$oldStatus = $postBefore ? $postBefore->post_status : null;
		if ($newStatus === $oldStatus) {
			return;
		}

		$postTypes = static::option('post-types');
		if (empty($postTypes[$post->post_type])) {
			return;
		}

		$webhookUrl = trim(static::option('webhook-url') ?? '');
		if (!$webhookUrl) {
			return;
		}

		$vars = new WP_DISCORD_POSTER_BOT_VARS(
			$post,
			get_user_by('ID', $post->post_author) ?: null
		);

		$content = trim($vars->format(static::option('format') ?? ''));

		$embed = [
			'title' => $vars->var('title'),
			'url' => $vars->var('url'),
			'description' => $vars->var('description'),
			'author' => [
				'name' => $vars->var('author')
			],
			'timestamp' => $vars->var('timestamp'),
			'footer' => [
				'text' => $vars->var('site_name'),
				'icon_url' => $vars->var('site_icon')
			]
		];

		$image = $vars->var('image');
		if ($image) {
			$embed['image'] = [
				'url' => $image
			];
		}

		wp_remote_post($webhookUrl, [
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode([
				'content' => $content,
				'embeds' => [$embed]
			])
		]);
	}
}
WP_DISCORD_POSTER_BOT_MAIN::init();
