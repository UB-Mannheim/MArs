// mars.js
function onlyOne(checkbox,cname) {
    var checkboxes = document.getElementsByName(cname);
    checkboxes.forEach((item) => {
        if (item !== checkbox) {
	    item.checked = false;
        };
    });
};


(function () {
    function checkLikeRadio(tag) {
        var form = document.getElementById(tag);//selecting the form ID
        var checkboxList = form.getElementsByTagName("input");//selecting all checkbox of that form who will behave like radio button
        for (var i = 0; i < checkboxList.length; i++) {//loop thorough every checkbox and set there value false.
            if (checkboxList[i].type == "checkbox") {
                checkboxList[i].checked = false;
            }
            checkboxList[i].onclick = function () {
                checkLikeRadio(tag);//recursively calling the same function again to uncheck all checkbox
                checkBoxName(this);// passing the location of selected checkbox to another function.
            };
        }
    }

    function checkBoxName(id) {
        return id.checked = true;// selecting the selected checkbox and maiking its value true;
    }
    window.onload = function () {
        checkLikeRadio("form");
    };
})();