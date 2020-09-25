// mars.js
(function () {
function onlyOne(checkbox,cname) {
    var checkboxes = document.getElementsByName(cname);
    checkboxes.forEach((item) => {
        if (item.id !== checkbox) {
            item.checked = false;
        };
    });
};


    function checkLikeRadio(tag) {
        var form = document.getElementById(tag);//selecting the form ID
        var checkboxList = form.getElementsByTagName("input");//selecting all checkbox of that form who will behave like radio button
	var cname;
        for (var i = 0; i < checkboxList.length; i++) {//loop thorough every checkbox and set there value false.
	    cname = checkboxList[i].name;
            if (checkboxList[i].type == "checkbox") {
                //checkboxList[i].checked = false;
		cname = checkboxList[i].name;
           
		checkboxList[i].onclick = function (thisObject) {
                    //checkLikeRadio(tag);//recursively calling the same function again to uncheck all checkbox
                    //checkBoxName(this);// passing the location of selected checkbox to another function.
		    var cName = thisObject.currentTarget.name;
		    onlyOne(thisObject.currentTarget.id,cName);
                };
            };
        }
    }

    function checkBoxName(id) {
        return id.checked = true;// selecting the selected checkbox and maiking its value true;
    }
    window.onload = function () {
        checkLikeRadio("reservation");
    };
})();
