<# if ( !data.isSingle && !data.isPage && !data.is404 && !( data.isSearch && !data.havePosts ) ) { #>
	<h2 class="o2-app-page-title">
		<# if ( data.showTitle && data.pageTitle != '' ) { #>
			{{{ data.pageTitle }}}
		<# } #>
	</h2>
	<span class="o2-app-controls">
		{{{ data.appControls.join( ' | ' ) }}}
	</span>
<# } else { #>
	<# if ( data.showTitle && data.pageTitle != '' ) { #>
		<h2 class="o2-app-page-title">
			{{{ data.pageTitle }}}
		</h2>
	<# } #>
<# } #>
