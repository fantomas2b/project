// mON JS HEHHEHE++++/////////





function selectImg(paramClassName){

var allImgs=document.getElementsByTagName("img");
for (var i=0;i<allImgs.length;i++){
	allImgs[i].style.display="none";
  };

  if(paramClassName=="resetAll"){
  	for (var i=0;i<allImgs.length;i++){
	allImgs[i].style.display="inline-block";
    };

  };

var elems=document.getElementsByClassName(paramClassName);
for (var i=0;i<elems.length;i++){
	elems[i].style.display="inline-block";
  };

};




