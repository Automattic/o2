<header class="entry-header">
<# if ( ! data.isPage && o2.options.showAvatars && data.author.avatar ) { #>
	<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="author-avatar">
		<img src="{{ data.author.avatar }}" width="{{ data.avatarSize }}" height="{{ data.avatarSize }}" class="avatar" />
	</a>
<# } #>
	<div class="entry-meta">
<# if ( ! data.isPage ) { #>
		<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="entry-author">
			{{ data.author.displayName }}
		</a>
		<a href="{{ data.permalink }}" class="entry-date o2-timestamp" data-unixtime="{{ data.unixtime }}">
		</a>
<# } #>
		{{{ data.postActions }}}
		{{{ data.entryHeaderMeta }}}
	</div>
</header>
<div class="entry-content">
	<div class="o2-editor">
		<textarea title="{{ data.titleRaw }}" placeholder="" class="o2-editor">{{ data.contentRaw }}</textarea>
		<div class="o2-editor-footer">
			<ul class="o2-editor-tabs">
				<li class="selected"><a href="#" class="o2-editor-edit-button genericon-edit">{{ data.strings.edit }}</a></li>
				<li><a href="#" class="o2-editor-preview-button genericon-show">{{ data.strings.preview }}</a></li>
			</ul>

			<a href="#" class="o2-save primary" title="&#8984;-enter">{{ data.strings.save }}</a>
			<a href="#" class="o2-cancel">{{ data.strings.cancel }}</a>
		</div>
	</div>
</div>

<footer class="entry-meta">
	{{{ data.footerEntryMeta }}}
</footer>
