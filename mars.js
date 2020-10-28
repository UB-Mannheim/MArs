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
        var form = document.getElementById(tag);    //selecting the form ID
        var checkboxList = form.getElementsByTagName("input");  //selecting all checkbox of that form who will behave like radio button

        for (var i = 0; i < checkboxList.length; i++) {//loop thorough every checkbox and set there value false.
            if (checkboxList[i].type == "checkbox") {

                checkboxList[i].onclick = function (thisObject) {
                    var cName = thisObject.currentTarget.name;
                    var cId   = thisObject.currentTarget.id;
                    onlyOne(cId,cName);
                };
            };
        };
    };

    window.onload = function () {
        checkLikeRadio("reservation");
    };
})();
