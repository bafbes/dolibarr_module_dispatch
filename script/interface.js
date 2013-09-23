
function add_line(id_ligne,rang){
	newrang = rang + 1;
	ligne = $('.ligne_'+id_ligne+'_'+rang);
	//MAJ du rowspan pour la partie gauche de la ligne
	$('tr[class=ligne_'+id_ligne+'_'+rang+'] > td').each(function(){
		$(this).attr('rowspan',newrang);
	});
	
	//clonage de la ligne et suppression des td en trop
	newligne = $(ligne).clone(true).insertAfter($(ligne));
	cpt = 0;
	$(newligne).find('> td').each(function(){
		if(cpt <= 4)
			$(this).remove();
	});
}

function delete_line(ligne){
	
}
