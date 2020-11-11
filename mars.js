// mars.js
(function () {
    function onlyOne(checkboxId,cname,cClass) {
        var checkboxes = document.getElementsByName(cname);
        checkboxes.forEach((item) => {
            if (item.id !== checkboxId) {
                item.checked = false;
                if (item.ClassName == 'checked-input') {
                    // Unicode-Zeichen "U+2718" (|&#x2718;|
                    item.parentElement.lastChild.innerText = '×';
                }
            };
        });
    };

    function checkLikeRadio(tag) {
        var form = document.getElementById(tag);    //selecting the form ID
        var checkboxList = form.getElementsByTagName("input");  //selecting all checkbox of that form who will behave like radio button

        for (var i = 0; i < checkboxList.length; i++) {//loop thorough every checkbox and set there value false.
            if (checkboxList[i].type == "checkbox") {

                checkboxList[i].onclick = function (thisObject) {
                    var cName   = thisObject.currentTarget.name;
                    var cId     = thisObject.currentTarget.id;
                    var cClass  = thisObject.currentTarget.ClassName;
                    onlyOne(cId,cName,cClass);
                };
            };
        };
    };

    window.onload = function () {
        checkLikeRadio("reservation");
    };
})();
