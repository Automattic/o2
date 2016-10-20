<header class="o2-comment-header comment">
	<# if ( o2.options.showAvatars && data.author.avatar ) { #>
	<img src="{{ data.author.avatar }}" width="{{ data.avatarSize }}" height="{{ data.avatarSize }}" class="avatar {{ data.author.modelClass }}" />
	<# } #>
	<div class="comment-meta commentmetadata">
		<# if ( data.isAnonymousAuthor ) { #>
			<span class="comment-author">{{ data.strings.anonymous }}</span>
		<# } else { #>
			<a href="{{ data.author.url }}" rel="external nofollow" class="comment-author url">
				{{ data.author.displayName }}
			</a>
		<# } #>
		<a href="{{ data.permalink }}" class="comment-date o2-timestamp" data-unixtime="{{ data.unixtime }}"></a>
		<# if ( data.someoneElsesComment ) { #>
		<span class="o2-editing-others">{{ data.strings.editingOthersComment }}</span>
		<# } #>
	</div>
</header>
<div class="o2-editor">
	{{{ data.commentFormBefore }}}
	<textarea class="o2-editor">{{ data.contentRaw }}</textarea>
	<div class="o2-editor-footer">
		<ul class="o2-editor-tabs">
			<li class="selected"><a href="#" class="o2-editor-edit-button genericon-edit">{{ data.strings.edit }}</a></li>
			<li><a href="#" class="o2-editor-preview-button genericon-show">{{ data.strings.preview }}</a></li>
		</ul>

		<# if ( data.isNew ) { #>
			<a href="#" class="o2-comment-save primary" title="&#8984;-enter">{{ data.strings.reply }}</a>
			<a href="#" class="o2-new-comment-cancel">{{ data.strings.cancel }}</a>
		<# } else { #>
			<a href="#" class="o2-comment-save primary" title="&#8984;-enter">{{ data.strings.save }}</a>
			<a href="#" class="o2-comment-cancel">{{ data.strings.cancel }}</a>
		<# } #>

		<# if ( data.isNew ) { #>
			<div class="o2-comment-form-options">
				{{{ data.commentFormExtras }}}
				<div class="o2-comment-form-options-extra"></div>
			</div>
		<# } #>
	</div>
</div>
