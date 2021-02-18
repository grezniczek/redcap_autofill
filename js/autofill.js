var IVEM = IVEM || {};

// Initialize
IVEM.uploadComplete = IVEM.uploadComplete || [];

// Debug logging
IVEM.log = function() {
    if (IVEM.debug) {
        switch(arguments.length) {
            case 1: 
                console.log(arguments[0]); 
                return;
            case 2: 
                console.log(arguments[0], arguments[1]); 
                return;
            case 3: 
                console.log(arguments[0], arguments[1], arguments[2]); 
                return;
            case 4:
                console.log(arguments[0], arguments[1], arguments[2], arguments[3]); 
                return;
            default:
                console.log(arguments);
        }
    }
}

/**
 * On the online designer page, let's highlight those fields that are configured in the module setup
 */
IVEM.highlightFields = function() {
    $.each(IVEM.field_params, function(field, params) {
        var tr = $('tr[sq_id="' + field + '"]').not('.IVEM');
        if (tr.length) {
            var icon_div = $('.frmedit_icons', tr);
            var label = $('<div style="float:right;margin-right:1em;"><i class="far fa-eye"></i> <i>Image Viewer</i></div>')
                .addClass('label label-primary em-label text-dark')
                .attr('data-toggle', 'tooltip')
                .attr('title', 'The content of this field is customized by the Image Viewer External Module' + ( params ? ':\n' + JSON.stringify(params) : ''))
                .on('click', function() {
                    event.stopPropagation();
                })
                .appendTo(icon_div);
            tr.addClass('IVEM');
        }
    });
};

/**
 * Set up module on survey / data entry pages
 */
IVEM.init = function() {
    $(function() {

        // Hijack the proxy function for preview of images immediately after uploads
        IVEM.setupProxy();

        // Process each field on the page that already contains data when the page is loaded
        $.each(IVEM.preview_fields, function(field, params) {
            IVEM.insertPreview(field, params);
        });
    });
};

/**
 * Preview the file attached to the given field
 * This is called both on existing uploads when rendered and after new uploads are attached to fields
 * It relies on there being IVEM.field_params and IVEM.file_details
 * @param field
 */
IVEM.insertPreview = function(field, params) {

    var data = IVEM.preview_fields[field]
    // Get parent tr for table
    var tr = $('tr[sq_id="' + field + '"]');
    if (! tr.length) return;
    var td_label = tr.find('td.labelrc').last();

    // Get hash (surveys only)
    var hash = $('#form :input[name=__response_hash__]').val();

    // Get the hyperlink element (also handle descriptive fields)
    var a = $('a[name="' + field + '"], a.filedownloadlink', tr);
    var src = '';
    if (a.length) {
        // Get src from href
        src = a.attr('href');
        if (src == '') return;
    }
    else {
        // Build src for piped fields
        if (page.substr(0, 10) == 'DataEntry/') {
            src = app_path_webroot + 'DataEntry/file_download.php?pid=' + pid + '&page=' + data.page + '&doc_id_hash=' + data.hash + '&id=' + data.doc_id + '&ivem_preview=' + IVEM.payload + '&s=&record=' + data.record + '&event_id=' + data.event_id + '&field_name=' + data.field_name + '&instance=' + data.instance
        }
        else if (page.substr(0, 8) == 'surveys/') {
            src = app_path_webroot_full + page + '?pid=' + pid + '&__passthru=DataEntry%2Ffile_download.php&doc_id_hash=' + data.hash + '&id=' + data.doc_id + '&ivem_preview=' + IVEM.payload + '&s=' + data.survey_hash + '&record=' + data.record + '&page=&event_id=' + data.event_id + '&field_name=' + data.field_name + '&instance=' + data.instance
        }
    }

    // Append the response hash if needed (only for surveys)
    if (src.indexOf('__response_hash__') === -1 && hash) {
        src += '&__response_hash__=' + hash;
        IVEM.log('Appending response hash.');
    }

    // Determine the width of the parent/child TD
    var td_width = a.length ? a.closest('td').width() : td_label.width();
    IVEM.log('Processing', field, params.params);

    // A preview hash indicates that the file was just uploaded and must be previewed using the every_page_before_render hook
    // We will add the ivem_preview tag to the query string to distinguish this request
    if (params.hash) {
        src += '&ivem_preview=' + IVEM.payload;
    }


    // Create and insert a container
    // Check if it is already there, create otherwise
    var $container
    if (params.hasOwnProperty('container_id')) {
        $container = $('div[data-ivem-container=' + params.container_id + ']')
        if ($container.length == 0) {
            $container = $('<div></div>')
                .attr('data-ivem-container', params.container_id)
            if (params.piped) {
                $container.attr('data-ivem-pipe-source', params.pipe_source)
            }
            if (a.length) {
                a.before($container)
            }
            else {
                td_label.append($container)
            }
        }
    }
    else if (params.piped) {
        // Piping - get target containers
        $container = $('div[data-ivem-pipe-source=' + params.pipe_source + ']')
    }
    $container.each(function() {
        $this_cont = $(this)
        // Handle valid images
        if (IVEM.valid_image_suffixes.indexOf(params.suffix) !== -1)
        {
            // Create a new image element and shrink to fit wd_width.
            var $img = $('<img/>')
                .addClass('IVEM')
                .attr('src', src)
                .css('max-width', td_width + 'px')
                .css('margin-left', 'auto')
                .css('margin-right', 'auto')
                .css('display', 'block');
    
            // Append custom CSS if specified for the field
            $.each(params.params, function(k,v) {
                $img.css(k,v);
            });
            // Empty container and add image
            $this_cont.empty().append($img);
        }
        // Handle Valid PDF Files - https://github.com/pipwerks/PDFObject
        else if (IVEM.valid_pdf_suffixes.indexOf(params.suffix) !== -1)
        {
            src = src + '&stream=1';
            IVEM.log('Creating PDF with ' + src);
            var $pdf = $('<div/>').attr('id', field + '_pdfobject');
            // Empty container and add pdf
            $this_cont.empty().append($pdf)
            // Set default pdf options and load any custom options from the field params
            var options = { fallbackLink: 'This browser does not support inline PDFs' };
            $.extend(options, params.params);
            // Create object
            IVEM[field + '_pdf'] = PDFObject.embed(src, $pdf, options);
        }
    })
};

/**
 * Extract the file extension from a string or return empty
 * @param path
 * @returns {string}
 */
IVEM.getExtension = function (path) {
    var basename = path.split(/[\\/]/).pop(),  // extract file name from full path ...
        // (supports `\\` and `/` separators)
        pos = basename.lastIndexOf('.');       // get last position of `.`

    if (basename === '' || pos < 1)            // if file name is empty or ...
        return '';                             //  `.` not found (-1) or comes first (0)

    return basename.slice(pos + 1);            // extract extension ignoring `.`
};

/**
 * Add a notification to the Project Setup page
 */
IVEM.projectSetup = function () {
    $(function () {
        var first_box = $('#setupChklist-modify_project');
        if (first_box.length) {
            var element = $('#em_summary_box');
            if (!element.length) {
                element = $('<div id="em_summary_box" class="round chklist col-xs-12"><strong>External Modules: </strong></div>');
            }

            var label = $('<span>ImageViewer</span>')
                .addClass('label label-primary label-lg em-label')
                .attr('title', 'The content of this project is customized by the Image Viewer External Module');

            var badge = $('<span></span>')
                .text(IVEM.field_params.length)
                .addClass('badge')
                .appendTo(label);

            element.append(label);
            first_box.parent().append(element);
        }
    });
};

/**
 * This proxy allows the EM to update an image as soon as it is finished uploading the image without leaving the page.
 */
IVEM.setupProxy = function() {

    // Allows us to validate the modal dialog after it opens (could be done differently)
    var proxied_stopUpload = stopUpload
    // function stopUpload(success,this_field,doc_id,doc_name,study_id,doc_size,event_id,download_page,delete_page,doc_id_hash,instance)
    stopUpload = function () {
        // First do the standard stopUpload
        $result = proxied_stopUpload.apply(this, arguments)

        // After a successful upload, the download url is attached to the page - let's use it to download a preview image
        IVEM.log('Upload', arguments)
        var success = arguments[0]
        var field = arguments[1]
        var doc_name = arguments[3]
        var event_id = arguments[6]
        var instance = arguments[10]
        var suffix =  IVEM.getExtension(doc_name).toLowerCase()

        // This is file part of an active field
        if (success && IVEM.field_params.hasOwnProperty(field)) {
            IVEM.log('Upload to ' + field + ' with ' + doc_name + ', rendering preview')
            var params = {}
            params.params = IVEM.field_params[field]
            params.hash = arguments[9]
            params.piped = false
            params.suffix = suffix
            params.pipe_source = field + '-' + event_id + '-' + instance
            params.container_id = IVEM.preview_fields[field].container_id
            IVEM.insertPreview(field, params)
        }
        // This is file part that is piped
        if (success && IVEM.pipe_sources.hasOwnProperty(field)) {
            IVEM.log('Upload to ' + field + ' with ' + doc_name + ', rendering live pipe')
            var params = {}
            params.params = IVEM.field_params[field]
            params.hash = arguments[9]
            params.piped = true
            params.suffix = suffix
            params.pipe_source = field + '-' + event_id + '-' + instance
            IVEM.insertPreview(field, params)
        }
        // Add optional updateTrigger than can be called on completion of the upload
        if (IVEM.uploadComplete) {
            for (var i = 0; i < IVEM.uploadComplete.length; i++) {
                var t = IVEM.uploadComplete[i]
                if (typeof(t) === 'function') {
                    IVEM.log('Calling function')
                    t()
                }
            }
        }
        return $result
    }
    var proxied_deleteDocument = deleteDocument
    // function deleteDocument(doc_id,this_field,id,event_id,instance,delete_page,version,version_hash)
    deleteDocument = function() {
        // First do the standard deleteDocument
        $result = proxied_deleteDocument.apply(this, arguments)
        // Clear containers
        var field = arguments[1]
        var event_id = arguments[3]
        var instance = arguments[4]
        var pipe_source = field + '-' + event_id + '-' + instance
        $('div[data-ivem-container=ivem-' + pipe_source + ']').empty()
        $('div[data-ivem-pipe-source=' + pipe_source + ']').empty()
        IVEM.log('Deleted ' + pipe_source)
    }
};
