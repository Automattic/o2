<# if ( !data.isSingle && !data.isPage && !data.is404 && !( data.isSearch && !data.havePosts ) ) { #>
	<h2 class="o2-app-page-title">
		<# if ( data.showTitle && data.pageTitle != '' ) { #>
			{{{ data.pageTitle }}}
		<# } #>
		<span class="o2-app-controls">
			{{{ data.appControls.join( ' | ' ) }}}
		</span>
	</h2>
<# } else { #>
	<# if ( data.showTitle && data.pageTitle != '' ) { #>
		<h2 class="o2-app-page-title">
			{{{ data.pageTitle }}}
		</h2>
	<# } #>
<# } #>
