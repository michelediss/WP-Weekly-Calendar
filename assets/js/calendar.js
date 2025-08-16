(function(){
	function setQS(key,val){
		const u=new URL(window.location); if(val==null||val===''){u.searchParams.delete(key);}else{u.searchParams.set(key,val)}
		history.replaceState({},'',u);
	}
	document.addEventListener('click', function(e){
		const t = e.target.closest('[data-wpwc-attivita]');
		if(!t) return;
		e.preventDefault();
		const id = t.getAttribute('data-wpwc-attivita');
		setQS('attivita', id);
		window.location = window.location.href;
	});
})();
