<?php 

include './header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
    header("location: ./login/login.php?next=servers");
    exit;
}

include $root."/extra/connfuncs.php";

$VM = null;
foreach ($owned_vms as $pvm) { 
    if ($pvm['vmid'] == $data['vmid']) { 
        $VM = $pvm;
        break;
    }
}

if (empty($data) || $data['state'] != $_SESSION['state']) { 
    $errorMSG = "An error has occurred building the token! Please try again.";
    $returnPage = "servers.php";
    include 'extra/error.php';
    exit();

} else if ($data['action'] == 'conn_start') {

?>
<html>
    <head>
        <meta charset="UTF-8">
        <title>VM-<?php echo $data['title']; ?></title>
        <link rel="stylesheet" type="text/css" href="./guac/sweetalert2.min.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
        
        <!-- testing -->
        <link href="extra/css/vdi.css" rel="stylesheet">
    </head>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"  integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ="  crossorigin="anonymous"></script>
    
    <script type="text/javascript" src="guac/all.min.js"></script>
    <script type="text/javascript" src="guac/clipboard.min.js"></script>
    <script type="text/javascript" src="guac/mousetrap.min.js"></script>
    <script type="text/javascript" src="guac/sweetalert.min.js"></script>

    <style> 
        body {
            overflow-y: hidden !important; /* Hide vertical scrollbar */
            overflow-x: hidden !important; /* Hide horizontal scrollbar */
        }

        h3 { 
            display: block;
            font-size: 1.17em;
            margin-block-start: 1em;
            margin-block-end: 1em;
            margin-inline-start: 0px;
            margin-inline-end: 0px;
            font-weight: bold;
        }

        #display { 
            cursor: none;
        }

        .dropdown-toggle-menu::after {
            display: inline-block;
            margin-left: 2em;
            vertical-align: .255em;
            content: "";
            border-top: .3em solid;
            border-right: .3em solid transparent;
            border-bottom: 0;
            border-left: .3em solid transparent;
        }

        .navbar-sidemenu { 
            justify-content: space-between;
            flex-direction: row;
            margin: 0;
            padding: 0;
        }

        .menu-item { 
            margin-right: .5em;
        }

        .dropdown-menu-end[data-bs-popper] {
            right: -2.7em !important;
            left: auto;
        }

        .dropdown-menu-start[data-bs-popper] {
            left: -.75em !important;
            right: auto;
        }

        .dropdown-menu { 
            border-radius: 0px 0px 3px 3px !important;
        }
    </style>

    <body class="bg-dark text-light">
        <div id="sidebarMenu" class="offcanvas offcanvas-start text-dark stopcapture" tabindex="-1" aria-labelledby="sidebarLabel" style="background: #eee">
            <nav class="navbar-sidemenu navbar navbar-expand-lg bg-light nav-fill">
                <div class="container-fluid">
                    <div class="navbar-nav me-auto mb-2 mb-lg-0">
                        <div class="nav-item dropdown" style="width:75%;max-width:75%;">
                            <span class="nav-link dropdown-toggle-menu btn-group-justified fs-5 fw-bold" id="vmDropMenu" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo $data['title'].'-'.$_SESSION['username']; ?>
                            </span>
                            <div class="dropdown-menu dropdown-menu-start" aria-labelledby="vmDropMenu" style="margin: 0; padding: 0;">
                                <?php foreach($owned_vms as $pvm) { ?>
                                    <a class="dropdown-item" onclick="document.getElementById('<?php echo $pvm['vmid'];?>-conn').submit();" ><?php echo $pvm['name']; ?></a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="nav-item dropdown" style="width:20%;max-width:20%;">
                            <span class="nav-link dropdown-toggle" href="#" id="userDropMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="menu-item fa-solid fa-user"></i><span class="fs-5 fw-bold"><?php echo $_SESSION["username"]; ?></span>
                            </span>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropMenu" style="margin: 0; padding: 0;">
                                <a class="dropdown-item bg-danger" href="#" onclick="guac.disconnect()"><i class="fa-solid fa-xmark pe-2"></i>Disconnect </a>
                                <a class="dropdown-item" href="servers.php"> <i class="fa-solid fa-home pe-2"></i>Home </a>
                                <a class="dropdown-item" href="settings.php"> <i class="fa-solid fa-gear pe-2"></i>Settings </a>
                                <a class="dropdown-item" href="../login/logout.php"> <i class="fa-solid fa-delete-left pe-2"></i>Logout </a>
                            </div>
                        </div>
                    </div>
                    <?php foreach($owned_vms as $pvm) { ?>
                        <form id="<?php echo $pvm['vmid'];?>-conn" action="connect.php" method="POST" formtarget="_blank">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect($pvm['token']); ?>">
                            <input id="connection_width" type="hidden" name="width" value="1024">
                            <input id="connection_height" type="hidden" name="height" value="720">
                        </form>
                    <?php } ?>
                </div>
            </nav>
            <div class="offcanvas-body">
                <div class="d-grid gap-3">
                    <div class="p-1">
                        <h3>Clipboard</h3>
                        <p>Text copied/cut within Guacamole will appear here. Changes to the text below will affect the remote clipboard.</p>
                        <textarea id="clipboard" class="form-control" rows="5" style="width: 100% !important"></textarea>
                    </div>
                    <div class="p-1">
                        <h3>Actions</h3>
                        <?php if ($VM['status'] == 'running') { ?>
                            <a class="btn btn-danger" href="connect.php?data=<?php echo $requestHandler->protect(array('quickact', 'stop', $VM['vmid'], $requestHandler->protect($VM['token']))); ?>">Stop</a>
                            <a class="btn btn-warning" href="connect.php?data=<?php echo $requestHandler->protect(array('quickact', 'restart', $VM['vmid'], $requestHandler->protect($VM['token']))); ?>">Restart</a>
                        <?php } else if ($VM['status'] == 'paused'){ ?>
                            <a class="btn btn-success" href="connect.php?data=<?php echo $requestHandler->protect(array('quickact', 'resume', $VM['vmid'], $requestHandler->protect($VM['token']))); ?>">Resume</a>
                        <?php } else { ?>
                            <a class="btn btn-success" href="connect.php?data=<?php echo $requestHandler->protect(array('quickact', 'start', $VM['vmid'], $requestHandler->protect($VM['token']))); ?>">Start</a>
                        <?php } ?>
                        <a class="btn btn-primary" href="#" onclick="document.getElementById('<?php echo $VM['vmid'];?>-conn').submit();">Reconnect</a>
                    </div>
                    <div class="p-1">
                        <h3> Snapshots </h3>
                        <form id="snapshotcreate" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('snapman', $_SESSION['username'], $VM['vmid'])); ?>"> 
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="snapname" value="quicksnap<?php echo time(); ?>">
                            <input type="hidden" name="description" value="">
                            <input type="hidden" name="vmid" value="<?php echo $VM['vmid']; ?>">
                            <input type="hidden" name="return_data" value="<?php echo $requestHandler->protect($VM['token']); ?>">
                        </form>
                        <a href="#" onclick="document.getElementById('snapshotcreate').submit();"  class="btn btn-primary">Quick Snapshot</a>
                        <a href="connect.php?data=<?php echo $requestHandler->protect(array('quickact', 'revert', $VM['vmid'], $requestHandler->protect($VM['token']))); ?>" class="btn btn-primary">Quick Revert</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="startcapture" id="display">
            <?php if (isset($data['alert'])) { ?>
                <div class="alert alert-<?php echo $data['alert']['color']; ?> alert-dismissible fade show" role="alert">
                    <strong><?php echo $data['alert']['title']; ?></strong> <?php echo $data['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
        </div>  

        <script type="text/javascript">
            var menuCanvas = document.getElementById("sidebarMenu");
            var sideBarCanvas = new bootstrap.Offcanvas(menuCanvas, {backdrop: false})
            var clipboard = new ClipboardJS('#clipboard');
            var ignoreGuacInput = false;
            var fileSystem;
            var errorRecv = null;

            function getWidth() {
                return Math.max(
                    document.body.scrollWidth,
                    document.documentElement.scrollWidth,
                    document.body.offsetWidth,
                    document.documentElement.offsetWidth,
                    document.documentElement.clientWidth
                );
            }

            function getHeight() {
                return Math.max(
                    document.body.scrollHeight,
                    document.documentElement.scrollHeight,
                    document.body.offsetHeight,
                    document.documentElement.offsetHeight,
                    document.documentElement.clientHeight
                ) - 10;
            }

            function saveAs(blob_data, download_file_name) {
                console.log('starting download of "' + download_file_name +'"');
                var a = document.createElement('a');
                var url = window.URL.createObjectURL(blob_data);
                var filename = download_file_name;
                a.href = url;
                a.download = filename;
                a.click();
                console.log('download request sent for "' + download_file_name +'"');
                window.URL.revokeObjectURL(url);
            }

            // Instantiate client, using an HTTP tunnel for communications.
            // http://guacamole.apache.org/doc/guacamole-common-js/Guacamole.WebSocketTunnel.html
            var ws_scheme = window.location.protocol == "https:" ? "wss" : "ws";
            var ws_path   = ws_scheme + '://<?php echo $data['server'].(!empty($data['port']) ? ':'.$data['port'] : ''); ?>';
            var tunnel    = new Guacamole.WebSocketTunnel(ws_path);
            var guac      = new Guacamole.Client(
                tunnel
            );

            var display   = document.getElementById("display");
            var clipboard = document.getElementById("clipboard");

            display.appendChild(guac.getDisplay().getElement());

            // Error handler
            guac.onerror = function(error) {

                if (error.code == 769 && error.message.includes('credentials?')) {
                    errorRecv = "<strong>" + error.message + "</strong>";
                } 
                console.log(error.message);
            };
            
            var conn_string = 'token=<?php echo $data['token']; ?>&width=' + getWidth() + '&height=' + getHeight() + '&dpi=94';
            conn_string += '&clipboard=1&dpi=64';
            guac.connect(conn_string); // connect to the guacamole instance

            // Disconnect on close
            window.onunload = function() {
                console.log('disconnecting guac session!');
                guac.disconnect();
            }

            function loading(msg){
                // (Fortress machine <==> client) During upload/download process, add interface prompts
                swal({ 
                    title: msg, 
                    text: "You can close the current prompt and the data transfer will continue in the background without being affected..",
                    type: 'info',
                    // timer: 8000,
                    confirmButtonText: "Okay",
                    // showConfirmButton: false
                })
            }
            
            guac.onfilesystem = function(object) {
                fileSystem = object;
            };

            guac.onfile = function(stream, mimetype, filename){
                stream.sendAck('OK', Guacamole.Status.Code.SUCCESS);  // Send ACK to the server
                reader = new Guacamole.BlobReader(stream, mimetype);
                swal({ 
                    title: "Downloading '" + filename + "'...",
                    type: 'info',
                    position: 'top',
                    toast: true,
                    showConfirmButton: true
                });

                reader.onend = function() {
                    var blob_data = reader.getBlob(); 
                    swal.close(); 
                    saveAs(blob_data, filename); 
                };
            }

            // Drag and upload the file to the RDP server \\tsclient\mapped disk\
            document.ondragover = function(event){
                // Drag and drop files to the window to block, so that the browser does not prompt to open/download
                return false;
            };
            
            document.ondrop = function(ev) {
                ev.preventDefault();
                if (ev.dataTransfer.items) {
                    for (var i = 0; i < ev.dataTransfer.items.length; i++) {
                        if (ev.dataTransfer.items[i].kind === 'file') {

                            var file = ev.dataTransfer.items[i].getAsFile();
                            var reader = new FileReader();

                            console.log('uploading file: ' + file.name);

                            swal({ 
                                title: "uploading '" + file.name + "' to the VM",
                                type: 'info',
                                position: 'top',
                                toast: true,
                                showConfirmButton: true

                            });

                            reader.onloadend = function fileContentsLoaded (e){
                                const stream = guac.createFileStream(file.type, file.name);
                                var bufferWriter = new Guacamole.ArrayBufferWriter(stream);
                                bufferWriter.sendData(reader.result);
                                bufferWriter.sendEnd();
                            };

                            reader.onend = function() { 
                                swal.close();
                            }

                            reader.readAsArrayBuffer(file);
                        }
                    }
                } else {
                    for (var i = 0; i < ev.dataTransfer.files.length; i++) {
                        console.log(ev.dataTransfer.files[i].name);
                    }
                }

                // Drag and drop files to the window to block, so that the browser does not prompt to open/download
                return false;

            };

            $('#clipboard').on('input', function() {
                if ($(this).val() != undefined){
                    var stream = guac.createClipboardStream("text/plain");
                    var writer = new Guacamole.StringWriter(stream);
                    writer.sendText($(this).val());
                    writer.sendEnd();
                }
            });

            menuCanvas.addEventListener('hidden.bs.offcanvas', function () {
                keyboard.onkeydown = function(keysym) {
                    guac.sendKeyEvent(1, keysym);
                };
                keyboard.onkeyup = function(keysym) {
                    guac.sendKeyEvent(0, keysym);
                };
            });

            menuCanvas.addEventListener('hide.bs.offcanvas', function () {
                keyboard.onkeydown = function(keysym) {
                    guac.sendKeyEvent(1, keysym);
                };
                keyboard.onkeyup = function(keysym) {
                    guac.sendKeyEvent(0, keysym);
                };
            });

            menuCanvas.addEventListener('shown.bs.offcanvas', function () {
                ignoreGuacInput = true;
                keyboard.onkeydown = null;
                keyboard.onkeyup = null;
            });

            $('.startCapture').click(function() {
                if (ignoreGuacInput) { 
                    keyboard.onkeydown = function(keysym) {
                        guac.sendKeyEvent(1, keysym);
                    };
                    keyboard.onkeyup = function(keysym) {
                        guac.sendKeyEvent(0, keysym);
                    };
                    sideBarCanvas.hide();
                    ignoreGuacInput = false;
                }
            });

            // Clipboard: RDP Server ==>> Browser Client
            guac.onclipboard = function(stream, mimetype) {
                var reader;
                // If the received data is text, read it as a simple string
                if (/^text\//.exec(mimetype)) {

                    reader = new Guacamole.StringReader(stream);

                    // Assemble received data into a single string
                    var data = '';
                    reader.ontext = function(text) {
                        // Get clipboard/copy text from RDP server
                        data += text;
                    };
                    // Set clipboard contents once stream is finished
                    reader.onend = function() {
                        // Update text fetched in RDP to client clipboard
                        const input = document.createElement('textarea');
                        document.body.appendChild(input);
                        input.textContent=data;
                        input.select();
                        document.execCommand("Copy"); // The browser executes the copy command
                        document.body.removeChild(input);
                        clipboard.value = data;
                    };
                }

                // Otherwise read the clipboard data as a Blob
                else {
                    reader = new Guacamole.BlobReader(stream, mimetype);
                    reader.onend = function() {
                        // Copy the blob data (usually a picture) to the client clipboard
                        var blob_data = reader.getBlob();
                        clipboard.value = atob(blob_data);
                        
                        // Chrome only triggers the current function when text is copied, others do not trigger, for unknown reasons.
                    };
                }
            };

            var ws_conning = false;
            function reconnect(title){
                if (! title) {title='Connection Terminated!'}
                document.body.style.cursor = 'pointer';
                if (errorRecv == null) {
                    errorRecv = "Someone has either logged on, or the connection has been terminated. Would you like to reconnect?";
                }
                swal({
                    title: title,
                    html: errorRecv,
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Reconnect',
                    cancelButtonText: 'Home'
                }).then((result) => {
                    if (result.value) {
                        document.location.reload();
                    } else {
                        window.location.href = '/';
                    }
                })
            }

            // tunnel state change
            tunnel.onstatechange = function(state){
                //var STATE_CONNECTED     = 1;
                //var STATE_DISCONNECTED  = 2;
                if (state == 1){
                    ws_conning = true;
                } else if (ws_conning && state == 4){
                    reconnect(title='Connection Disconnecting!');
                } else if (ws_conning && state == 5) {
                    reconnect(title='Connection Closed!');
                } else if (state == 2) {
                    reconnect(title='Connection Timedout!');
                }
            }
            
            // guacamole connection state change
            guac.onstatechange = function(state){
                //alert closed
                //var STATE_IDLE          = 0;
                //var STATE_CONNECTING    = 1;
                //var STATE_WAITING       = 2;
                //var STATE_CONNECTED     = 3;
                //var STATE_DISCONNECTING = 4;
                //var STATE_DISCONNECTED  = 5;

                if (state == 5){
                    reconnect();
                } else if (state == 3){
                    console.log('connected to host <?php echo $data['title'].'-'.$_SESSION['username']; ?> using guac!');
                    guac.sendSize(window.innerWidth-10, window.innerHeight-60);
                    $(window).resize(function(){
                        guac.sendSize(window.innerWidth-10, window.innerHeight-60);

                    });
                } else if (state == 1 || state == 2){
                }               
            }

            // Teleport mouse action
            var mouse = new Guacamole.Mouse(guac.getDisplay().getElement());
            mouse.onmousedown =
            mouse.onmouseup   =
            mouse.onmousemove = function(mouseState) {
                guac.sendMouseState(mouseState);
            };

            // Teleport keyboard action
            var keyboard = new Guacamole.Keyboard(document);
            keyboard.onkeydown = function (keysym) {
                guac.sendKeyEvent(1, keysym);
            };
            keyboard.onkeyup = function (keysym) {
                guac.sendKeyEvent(0, keysym);
            };
            
            // clipboard: textarea text ==>> RDP server
            Mousetrap.bind(
                ['ctrl+shift+alt', 'ctrl+alt+shift', 'shift+alt+ctrl'],
                function(e) {
                    sideBarCanvas.toggle();
                }
            );

        </script>
        <noscript>
            <p>
                <center>JavaScript is required to use CollabVM. Either your browser is too old to run this website or you have disabled JavaScript from running.</center>
            </p>
        </noscript>
        <noscript>
            <p>
                <center>Please enable JavaScript in your browser and try again. If you still cannot connect, please try a newer browser (or try disabling some extensions) and try again.</center>
            </p>
    </noscript>
    </body>
</html>
<?php } ?>