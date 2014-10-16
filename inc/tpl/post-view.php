<div class="o2-post"></div>
<div class="o2-post-comments"></div>
<div class="o2-post-comment-controls"></div>

<# if ( data.showNavigation ) { #>
	<div class="navigation">
		<# if ( data.hasPrevPost ) { #>
			<p class="nav-older">
				<a href="{{ data.prevPostURL }}" title="{{ data.prevPostTitle }}" >&larr; {{{ data.prevPostTitle }}}</a>
			</p>
		<# } #>
		<# if ( data.hasNextPost ) { #>
			<p class="nav-newer">
				<a href="{{ data.nextPostURL }}" title="{{ data.nextPostTitle }}" >{{{ data.nextPostTitle }}} &rarr;</a>
			</p>
		<# } #>
	</div>
<# } #>
