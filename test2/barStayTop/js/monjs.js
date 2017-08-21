		$(function(){
			// On recupere la position du bloc par rapport au haut du site
			var position_top_raccourci = $("#topnav").offset().top;
			//afficher valeur en px pour atteindre le haut de la page
			console.log(position_top_raccourci);
			
			//Au scroll dans la fenetre on d�clenche la fonction
			$(window).scroll(function () {
			
				//si on a defile de plus de 150px du haut vers le bas
				if ($(this).scrollTop() > position_top_raccourci) {
				
					//on ajoute la classe "fixNavigation" a <div id="navigation">
					$('#topnav').addClass("fixNavigation"); 
				} else {
				
					//sinon on retire la classe "fixNavigation" a <div id="navigation">
					$('#topnav').removeClass("fixNavigation");
				}
			});
		});


		