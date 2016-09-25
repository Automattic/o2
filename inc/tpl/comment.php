<header class="o2-comment-header comment">
	<# if ( ! data.isSaving ) { #>
		<# if ( data.isTrashed && data.trashedSession ) { #>
			{{{ data.commentTrashedActions }}}
		<# } else { #>
			{{{ data.commentDropdownActions }}}
		<# } #>
	<# } #>

	<# if ( o2.options.showAvatars && data.author.avatar ) { #>
	<img src="{{ data.author.avatar }}" width="{{ data.avatarSize }}" height="{{ data.avatarSize }}" class="avatar {{ data.author.modelClass }}" />
	<# } #>
	<div class="comment-meta commentmetadata o2-comment-metadata" data-o2-comment-id="{{ data.id }}">
		<# if ( data.isAnonymousAuthor ) { #>
			<span class="comment-author">{{ data.strings.anonymous }}</span>
		<# } else { #>
			<a href="{{ data.author.url }}" rel="external nofollow" class="comment-author url {{ data.author.modelClass }}">
				{{ data.author.displayName }}
			</a>
		<# } #>
		<a href="{{ data.permalink }}" class="comment-date o2-timestamp" data-unixtime="{{ data.unixtime }}" data-compact-allowed="true"></a>
		<# if ( ! ( data.isNew || data.isSaving ) ) { #>
			<span class="comment-actions o2-actions">
				<# if ( data.currentUser.userLogin.length ) { #>
					<# if ( data.commentingAllowed ) { #>
						<a href="#" class="o2-comment-reply" title="{{ data.strings.reply }}">{{ data.strings.reply }}</a>
					<# } #>
					<# if ( data.editingAllowed ) { #>
						<a href="{{{ data.editURL }}}" class="o2-comment-edit" title="{{ data.strings.edit }}">{{ data.strings.edit }}</a>
					<# } #>
				<# } else { #>
					<# if ( data.commentingAllowed ) { #>
						<# if ( data.userMustBeLoggedInToComment ) { #>
							<a href="{{ data.loginRedirectURL }}" class="o2-reply-not-logged-in" title="{{ data.strings.loginToComment }}">{{ data.strings.loginToComment }}</a>
						<# } else { #>
							<a href="#" class="o2-comment-reply" title="{{ data.strings.reply }}">{{ data.strings.reply }}</a>
						<# } #>
					<# } #>
				<# } #>
			</span>
		<# } #>
	</div>
</header>
<div class="comment-content">
	<# if ( data.isTrashed ) { #>
		<p class="o2-comment-awaiting-approval">
			{{ data.strings.isTrashed }}
		</p>
	<# } else if ( ! data.approved ) { #>
		<p class="o2-comment-awaiting-approval">
			{{ data.strings.awaitingApproval }}
		</p>
	<# } else if ( data.prevDeleted ) { #>
		<p class="o2-comment-awaiting-approval">
			{{ data.strings.prevDeleted }}
		</p>
	<# } else { #>
		{{{ data.contentFiltered }}}
		{{{ data.commentFooterActions }}}
	<# } #>
</div>

<# if ( data.isNew || ( data.isSaving && ! data.isTrashedAction ) ) { #>
	<div class="o2-save-spinner"></div>
<# } #>
