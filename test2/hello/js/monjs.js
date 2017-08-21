

function changeClass(paramClassName){


var yoyo=document.getElementsByClassName(paramClassName);

var hehe=document.getElementsByTagName('nav');

for(var i=0;i<hehe.length;i++){

hehe[i].style.display="none";

};



for (var i=0;i<yoyo.length;i++){

	yoyo[i].style.display="inline";

	
	};

};








