// mars.js
function onlyOne(checkbox,cname) {
    var checkboxes = document.getElementsByName(cname);
    checkboxes.forEach((item) => {
        if (item !== checkbox) {
	    item.checked = false;
        };
    });
};
