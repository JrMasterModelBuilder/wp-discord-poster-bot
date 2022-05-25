<?php

/**
 * Variable getter.
 */
class WP_DISCORD_POSTER_BOT_VARS {
	/**
	 * Post object.
	 *
	 * @var WP_Post $post
	 */
	protected $post;

	/**
	 * Post author.
	 *
	 * @var WP_User|null $author
	 */
	protected $author;

	/**
	 * Var cache.
	 *
	 * @var array $cache
	 */
	protected $cache = [];

	/**
	 * Instance constructor.
	 *
	 * @param WP_Post $post
	 * @param WP_User|null $author
	 */
	public function __construct(WP_Post $post, ?WP_User $author) {
		$this->post = $post;
		$this->author = $author;
	}

	/**
	 * Get variable, with caching.
	 *
	 * @param string $name Variable name.
	 * @return string Variable value.
	 */
	public function var($var) {
		if (array_key_exists($var, $this->cache)) {
			return $this->cache[$var];
		}

		$method = "get_$var";
		$value = method_exists($this, $method) ? $this->$method() : null;
		$this->cache[$var] = $value;
		return $value;
	}

	/**
	 * Get title.
	 *
	 * @return string Post title.
	 */
	protected function get_title(): string {
		return static::html_decode(get_the_title($this->post));
	}

	/**
	 * Get author.
	 *
	 * @return string Author name.
	 */
	protected function get_author(): string {
		return static::html_decode(
			apply_filters(
				'the_author',
				$this->author ? $this->author->display_name : null
			)
		);
	}

	/**
	 * Get url.
	 *
	 * @return string Post URL.
	 */
	protected function get_url(): string {
		return apply_filters(
			'the_permalink',
			get_permalink($this->post),
			$this->post
		);
	}

	/**
	 * Get post_type.
	 *
	 * @return string Post type.
	 */
	protected function get_post_type(): string {
		$pt = get_post_type_object($this->post->post_type);
		return $pt ? static::html_decode($pt->labels->singular_name) : '';
	}

	/**
	 * Get description.
	 *
	 * @return string Post excerpt.
	 */
	protected function get_description(): string {
		$text = strip_shortcodes($this->post->post_content);
		$text = apply_filters('the_content', $text);
		$text = str_replace(']]>', ']]&gt;', $text);
		$text = html_entity_decode($text);
		$excerpt_length = apply_filters('excerpt_length', 55);
		$excerpt_more = apply_filters('excerpt_more', ' ...');
		$text = wp_trim_words($text, $excerpt_length, $excerpt_more);
		return trim(strip_tags($text));
	}

	/**
	 * Get timestamp.
	 *
	 * @return string Post excerpt.
	 */
	protected function get_timestamp(): string {
		return get_the_date('c', $this->post);
	}

	/**
	 * Get image.
	 *
	 * @return string Post thumbnail image URL, or null.
	 */
	protected function get_image(): ?string {
		if (!has_post_thumbnail($this->post)) {
			return null;
		}
		$id = get_post_thumbnail_id($this->post);
		$src = wp_get_attachment_image_src($id, 'full');
		return $src ? $src[0] : null;
	}

	/**
	 * Get site_name.
	 *
	 * @return string Site name.
	 */
	protected function get_site_name(): string {
		return trim(get_bloginfo('name'));
	}

	/**
	 * Get site_icon.
	 *
	 * @return string Site icon URL.
	 */
	protected function get_site_icon(): string {
		return get_site_icon_url('name');
	}

	/**
	 * Format string.
	 *
	 * @param string $format Format string.
	 * @return string Formatted string.
	 */
	public function format(string $format): string {
		$self = $this;
		return preg_replace_callback(
			'/%([a-zA-Z0-9-_]*)%/',
			function ($match) use ($self) {
				$v = $match[1];
				return $v === '' ? '%' : $self->var($v) ?? '';
			},
			$format
		);
	}

	/**
	 * List variables.
	 */
	public static function vars(): array {
		$r = [];
		foreach (get_class_methods(static::class) as $m) {
			if (substr($m, 0, 4) === 'get_') {
				$r[] = substr($m, 4);
			}
		}
		return $r;
	}

	/**
	 * Decode HTML to string.
	 *
	 * @param string $html HTML string.
	 * @return string Decoded string.
	 */
	protected static function html_decode(string $html): string {
		return trim(html_entity_decode(strip_tags($html)));
	}
}
