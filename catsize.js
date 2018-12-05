function coursesize (id) {
    if (document.getElementById('course' + id).style.display == 'block') {
        document.getElementById('course' + id).style.display = 'none';
        document.getElementById('iconcourse' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_plus.gif" width="16" height="16" onclick="coursesize('+id+')" style="cursor:pointer">';
    } else {
        if (document.getElementById('course' + id).innerHTML == '')
        {
            if (id != 0) {
                document.getElementById('iconcourse' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/loading_spinner.gif" width="16" height="16">';
            }
            YUI().use('io-base', function(Y, id2){
                Y.io(M.cfg.wwwroot +
                '/report/coursesize/callback.php?id='+id+'&course=1&sorder='+csize_sortorder+'&sdir='+csize_sortdir+'&display='+csize_displaysize+'&excludebackups='+csize_excludebackups, {
                on: {
                    complete: function(id3, e) {
                        var json = JSON.parse(e.responseText);
                        document.getElementById('course' + id).innerHTML = json;
                        document.getElementById('course' + id).style.display = 'block';
                        if (id != 0)
                        {
                            document.getElementById('iconcourse' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_minus.gif" width="16" height="16" onclick="coursesize('+id+')" style="cursor:pointer">';
                        }
                    }
                }
                });
            });
        } else {
            document.getElementById('course' + id).style.display = 'block';
            if (id != 0)
            {
                document.getElementById('iconcourse' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_minus.gif" width="16" height="16" onclick="coursesize('+id+')" style="cursor:pointer">';
            }
        }
    }
}

function catsize (id) {
    if (document.getElementById('cat' + id).style.display == 'block') {
        document.getElementById('cat' + id).style.display = 'none';
        document.getElementById('icon' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_plus.gif" width="16" height="16" onclick="catsize('+id+')" style="cursor:pointer">';
    } else {
        if (document.getElementById('cat' + id).innerHTML == '')
        {
            if (id != 0) {
                document.getElementById('icon' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/loading_spinner.gif" width="16" height="16">';
            }
            YUI().use('io-base', function(Y, id2){
                Y.io(M.cfg.wwwroot +
                '/report/coursesize/callback.php?id='+id+'&sorder='+csize_sortorder+'&sdir='+csize_sortdir+'&display='+csize_displaysize+'&excludebackups='+csize_excludebackups, {
                on: {
                    complete: function(id3, e) {
                        var json = JSON.parse(e.responseText);
                        document.getElementById('cat' + id).innerHTML = json;
                        document.getElementById('cat' + id).style.display = 'block';
                        if (id != 0)
                        {
                            document.getElementById('icon' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_minus.gif" width="16" height="16" onclick="catsize('+id+')" style="cursor:pointer">';
                        }
                    }
                }
                });
            });
        } else {
            document.getElementById('cat' + id).style.display = 'block';
            if (id != 0)
            {
                document.getElementById('icon' + id).innerHTML = '<img src="'+M.cfg.wwwroot + '/report/coursesize/pix/switch_minus.gif" width="16" height="16" onclick="catsize('+id+')" style="cursor:pointer">';
            }
        }
    }
}

catsize(0);