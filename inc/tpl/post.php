<header class="entry-header">
	<div class="entry-meta">
	<# if ( ! data.isPage && o2.options.showAvatars && data.author.avatar ) { #>
		<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="author-avatar {{ data.author.modelClass }}">
			<img src="{{ data.author.avatar }}" alt="" width="{{ data.author.avatarSize }}" height="{{ data.author.avatarSize }}" class="avatar {{ data.author.modelClass }}" />
		</a>
	<# } #>
	<# if ( ! data.isPage ) { #>
		<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="entry-author {{ data.author.modelClass }}">
			{{ data.author.displayName }}
		</a>
		<a href="{{ data.permalink }}" class="entry-date o2-timestamp" data-unixtime="{{ data.unixtime }}" data-compact-allowed="true"></a>
	<# } #>
		<# if ( ! data.isSaving ) { #>
			{{{ data.postActions }}}
		<# } #>
		{{{ data.entryHeaderMeta }}}
	</div>
	<# if ( data.showTitle && ! data.titleWasGeneratedFromContent ) { #>
		<h1 class="entry-title">
			<# if ( data.linkTitle ) { #>
				<a href="{{ data.permalink }}">{{{ data.titleFiltered }}}</a>
			<# } else { #>
				{{{ data.titleFiltered }}}
			<# } #>
		</h1>
	<# } #>
</header>
<div class="entry-content">
	{{{ data.contentFiltered }}}
	{{{ data.linkPages }}}
</div>
<footer class="entry-meta">
	{{{ data.footerEntryMeta }}}
	<# if ( data.postID ) { #>
		<div class="o2-display-comments-toggle">
			<a href="#">
				<span class="genericon genericon-expand"></span>
				<span class="disclosure-text">{{ data.strings.showComments }}</span>
			</a>
		</div>
	<# } else { #>
		<div class="o2-save-spinner"></div>
	<# } #>
</footer>
