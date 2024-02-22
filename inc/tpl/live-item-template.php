<# if ( o2.options.showAvatars && data.author.avatar ) { #>
	<img src="{{ data.author.avatar }}" alt="" width="{{ data.author.avatarSize }}" height="{{ data.author.avatarSize }}" class="avatar o2-live-item-img {{ data.author.modelClass }}" />
<# } #>
<p class="o2-live-item-text"><a href="{{ data.permalink }}" data-domref="{{ data.domRef }}"
	<# if ( 'comment' === data.type ) { #>
		data-postid="{{ data.postID }}"
	<# } #>
	>{{{ data.title }}}</a>
	<br/>
	<span class="entry-date o2-timestamp" data-unixtime="{{ data.unixtime }}" data-domref="{{ data.domRef }}"
		<# if ( 'comment' === data.type ) { #>
			data-postid="{{ data.postID }}"
		<# } #>
	>
	</span>
</p>
<div class="o2-live-item-clear"></div>
