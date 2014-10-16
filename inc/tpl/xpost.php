<# if ( o2.options.showAvatars && data.author.avatar ) { #>
<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="author-avatar o2-xpost-avatar">
	<img src="{{ data.author.avatar }}" width="{{ data.author.avatarSize }}" height="{{ data.author.avatarSize }}" class="avatar {{ data.author.modelClass }}" />
</a>
<# } #>
<h4 class="o2-xpost-author">
	<a href="{{ data.author.url }}" title="{{ data.author.urlTitle }}" class="entry-author {{ data.author.modelClass }}">
		{{ data.author.displayName }}
	</a>
	<a href="{{ data.permalink }}" class="entry-date o2-xpost-entry-date o2-timestamp" data-unixtime="{{ data.unixtime }}">
	</a>
</h4>
<div class="post-content o2-xpost-content">
	{{{ data.contentFiltered }}}
</div>
