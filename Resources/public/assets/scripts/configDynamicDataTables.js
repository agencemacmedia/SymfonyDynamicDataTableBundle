//Sets some data that needs to be cached
//For now initialise the lower limit

var oCache = {
    iCacheLower: -1
};

//Function that sets a value for a key in an array

function fnSetKey(aoData, sKey, mValue) {
    for (var i = 0, iLen = aoData.length; i < iLen; i++) {
        if (aoData[i].name == sKey) {
            aoData[i].value = mValue;
        }
    }
}

//Function that gets a value from a specific key from an array

function fnGetKey(aoData, sKey) {
    for (var i = 0, iLen = aoData.length; i < iLen; i++) {
        if (aoData[i].name == sKey) {
            return aoData[i].value;
        }
    }
    return null;
}

//Pipeline function that catched every request made by the datatable
//checks if it already has the data cached if it has, loads the data from the cache
//else makes a new request for the next data set

function fnDataTablesPipeline(sSource, aoData, fnCallback) {

    //Variable initialisation

    var iPipe = 5;
    var bNeedServer = false;
    var sEcho = fnGetKey(aoData, "sEcho");
    var iRequestStart = fnGetKey(aoData, "iDisplayStart");
    var iRequestLength = fnGetKey(aoData, "iDisplayLength");
    var iRequestEnd = iRequestStart + iRequestLength;
    oCache.iDisplayStart = iRequestStart;


    //Checks to see if the lower limit is still at the default or if the new request start is under or over the
    //lower limit if yes it means that we need a new request

    if (oCache.iCacheLower < 0 || iRequestStart < oCache.iCacheLower || iRequestEnd > oCache.iCacheUpper) {
        bNeedServer = true;
    }

    //Checks to see if there was a request before this one and if we need a new request
    //to check if it worth to check if the the sorting has changed

    if (oCache.lastRequest && !bNeedServer) {
        for (var i = 0, iLen = aoData.length; i < iLen; i++) {
            //goes through all the received data for the colomns
            if (aoData[i].name != "iDisplayStart" && aoData[i].name != "iDisplayLength" && aoData[i].name != "sEcho") {
                //Checks if everything is the same
                if (aoData[i].value != oCache.lastRequest[i].value) {
                    bNeedServer = true;
                    break;
                }
            }
        }
    }

    //Caching the current request

    oCache.lastRequest = aoData.slice();

    //Check to see if theres a need for a new request

    if (bNeedServer) {

        //Check to see if the new request is before the currently loaded data
        //if yes starts the next request at index 0
        if (iRequestStart < oCache.iCacheLower) {
            iRequestStart = iRequestStart - (iRequestLength * (iPipe - 1));
            if (iRequestStart < 0) {
                iRequestStart = 0;
            }
        }

        //Caching the current request's parameters

        oCache.iCacheLower = iRequestStart;
        oCache.iCacheUpper = iRequestStart + (iRequestLength * iPipe);
        oCache.iDisplayLength = fnGetKey(aoData, "iDisplayLength");
        fnSetKey(aoData, "iDisplayStart", iRequestStart);
        fnSetKey(aoData, "iDisplayLength", iRequestLength * iPipe);

        //Request the new data

        $.getJSON(sSource, aoData, function (json) {

            //Splices the received data so that we only have the items for the current page

            oCache.lastJson = jQuery.extend(true, {}, json);
            if (oCache.iCacheLower != oCache.iDisplayStart) {
                json.data.splice(0, oCache.iDisplayStart - oCache.iCacheLower);
            }
            json.data.splice(oCache.iDisplayLength, json.data.length);

            fnCallback(json)
        });
    } else {

        //Gets the data from the cache
        json = jQuery.extend(true, {}, oCache.lastJson);
        json.sEcho = sEcho;
        //Splices the received data so that we only have the items for the current page
        json.data.splice(0, iRequestStart - oCache.iCacheLower);
        json.data.splice(iRequestLength, json.data.length);
        fnCallback(json);
        return;
    }
}

//Clear cache function

$.fn.dataTable.Api.register('clearPipeline()', function () {
    return this.iterator('table', function (settings) {
        settings.clearCache = true;
    });
});


//Receives the table as is
function configDataTableMultiSearch(settings) {

    if (typeof settings != 'undefined' && settings != null) {
        var table;
        if (typeof settings.table !== 'undefined' && settings.table != null) {
            table = settings.table;
        } else {
            return;
        }
        var dropdowns;
        if (typeof settings.dropdowns !== 'undefined' && settings.dropdowns != null) {
            dropdowns = settings.dropdowns;
        }
    }else
    {
        return;
    }
    //gets the tableID
    var tableId = table.table().node().id;
    $('#' + tableId + ' tfoot').css('display', 'table-header-group');
    $('#' + tableId + '_wrapper .dataTables_filter').css('display', 'none');

    var specificClasses = [];
    var ddClasses = [];
    var globalClasses = "";
    if (typeof settings.classes !== 'undefined' && settings.classes != null) {
        if (typeof settings.classes.specific !== 'undefined') {
            specificClasses = settings.classes.specific;
        }
        if (typeof settings.classes.global !== 'undefined') {
            if (typeof settings.classes.global.all !== 'undefined') {
                for (var i = 0; i < settings.classes.global.all.length; i++) {
                    globalClasses += settings.classes.global.all[i] + " ";
                }
                globalClasses = globalClasses.trim();
            }
            if (typeof settings.classes.global.dropdown !== 'undefined') {
                ddClasses = settings.classes.global.dropdown;
            }
        }
    }


    var cols = table.context[0].oAjaxData.sColumns.split(",");
    var pos = 1;
    //Loops through all the columns of the table to set the new inputs
    $('#' + tableId + ' tfoot th').each(function () {
        var title = $(this).text();
        var isDropDown = false;
        if (typeof dropdowns !== 'undefined') {

            //Gets the classes for this column
            var ddParam = globalClasses + " ";
            for (var i = 0; i < ddClasses.length; i++) {
                ddParam += ddClasses[i] + " ";
            }
            if (specificClasses[pos - 1]) {
                ddParam += specificClasses[pos - 1] + " ";
            }
            if (specificClasses[cols[pos - 1]]) {
                ddParam += specificClasses[cols[pos - 1]] + " ";
            }
            ddParam = ddParam.trim();

            if (cols[pos - 1] in dropdowns) {
                if (ddParam !== "") {
                    $(this).html("<select id='" + tableId + "_input_" + pos + "' class='" + ddParam + "' style='font-family:Arial, FontAwesome; width:100%'> </select>");
                } else {
                    $(this).html("<select id='" + tableId + "_input_" + pos + "' class='form-control' style='font-family:Arial, FontAwesome; width:100%'> </select>");
                }
                var options = dropdowns[cols[pos - 1]];
                $("#" + tableId + "_input_" + pos).append("<option value='' selected ></option>");
                for (var i = 0; i < options.length; i++) {
                    $("#" + tableId + "_input_" + pos).append("<option value='" + options[i] + "'>" + options[i] + "</option>");
                }

                isDropDown = true;
            }
        }
        if (!isDropDown) {

            //Gets the classes for this column
            var inputParam = globalClasses + " ";
            if (specificClasses[pos - 1]) {
                inputParam += specificClasses[pos - 1] + " ";
            }
            if (specificClasses[cols[pos - 1]]) {
                inputParam += specificClasses[cols[pos - 1]] + " ";
            }
            inputParam = inputParam.trim();

            //If theres a class paramater apply it
            if (inputParam !== "") {
                $(this).html("<input id='" + tableId + "_input_" + pos + "' type='text' class='" + inputParam + "' placeholder='' style='font-family:Arial, FontAwesome; width:100%'/>");
            } else {
                $(this).html("<input id='" + tableId + "_input_" + pos + "' type='text' class='form-control' placeholder='' style='font-family:Arial, FontAwesome; width:100%'/>");
            }
        }
        pos++;
    });

    pos = 1;
    //Goes through all the newly created input and
    //associate a keyup event
    table.columns().every(function () {
        var that = this;
        if (!(cols[pos - 1] in dropdowns)) {
            $("#" + tableId + "_input_" + pos).on('keyup change', function () {
                //To wait for the full user input instead of making
                //a new request for every key press
                clearTimeout(typingTimer);
                typingTimer = setTimeout(searchTimeoutMulti, doneTypingInterval, that, this);

            });
        } else {
            $("#" + tableId + "_input_" + pos).change(function () {
                searchTimeoutMulti(that, this);
            });
        }
        pos++;
    })
    //Timeout function
    var typingTimer;
    //Timeout milisecond delay
    var doneTypingInterval = 800;

    //Timeout callback that initialise the search
    function searchTimeoutMulti(column, target) {
        if (column.search() !== target.value) {
            column.search(target.value).draw();
        }
    }

}

//Receives the table as is
function configDataTableSingleSearch(table) {

    //Timeout milisecond delay
    var typingTimer;
    //Timeout milisecond delay
    var doneTypingInterval = 800;
    //gets the tableID
    var tableId = table.table().node().id;
    //Keeps the search value
    var search;

    //Unbinds the old search event
    $("#" + tableId + "_filter input").unbind();

    //New keyup event
    $("#" + tableId + "_filter input").keyup(function () {
        search = this.value;
        //To wait for the full user input instead of making
        //a new request for every key press
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () {
            table.search(search).draw();
        }, doneTypingInterval);
    });

}