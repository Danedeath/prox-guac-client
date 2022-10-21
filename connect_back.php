<?php 

include './header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
    header("location: ./login/login.php");
    exit;
}

$data = array(); 

if (isset($_POST['data'])) {
    $temp = $requestHanlder->unprotect(filter_var($_POST['data'], FILTER_SANITIZE_STRING));

    $data = array(
        'state'  => $temp[0],
        'action' => $temp[1],
        'title'  => $temp[2],
        'server' => $INFO['guacd_host'],
        'port'   => $INFO['guacd_port'],
        'token'  => $temp[3]
    );
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
    </style>

    <body class="bg-dark text-light">

        <div class="modal left fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <center>
                <button type="button" style="cursor:pointer;" class="btn btn-secondary" data-toggle="modal" data-target="#Keyboard" onclick="poposk()">
                    <i class="fa fa-keyboard-o">Touch</i>
                </button>
            </center>
            <!-- Clipboard -->
            ClipBoard:<br>
            <textarea id="clipboard" class="stopcapture form-control" rows="7"></textarea>
            <!-- Resolution Management -->
            Browser Size:<br>
            <input id="currentsize" readonly="readonly" class="form-control stopcapture">
            Resize Desktop:<br>
            <input  list="resolutions" name="resolutions" class="form-control stopcapture" id="resform" placeholder="enter new resolution">
            <datalist id="resolutions"></datalist>
        </div>
        <div id="display"></div>  

        <script type="text/javascript"> /* <![CDATA[ */
            // hide the cursor: 
            document.body.style.cursor = 'none';

            // convert unicode
            function encodeUnicode(str) {
                var res = [];
                for ( var i=0; i<str.length; i++ ) {
                    res[i] = ( "00" + str.charCodeAt(i).toString(16) ).slice(-4);
                }
                return "\\u" + res.join("\\u");
            }

            // decode unicode
            function decodeUnicode(str) {
                str = str.replace(/\\/g, "%");
                return unescape(str);
            }

            function takeScreenShot(guac){
                var display = guac.getDisplay();
                if (display && display.getWidth() > 0 && display.getHeight() > 0) {
                    var canvas = display.flatten();
                    return canvas.toBlob(done);
                }
            }

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

            // Instantiate client, using an HTTP tunnel for communications.
            //http://guacamole.apache.org/doc/guacamole-common-js/Guacamole.WebSocketTunnel.html
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
                console.log(error);
            };
            
            var conn_string = 'token=<?php echo $data['token']; ?>&width=' + getWidth() + '&height=' + getHeight() + '&dpi=64';
            conn_string += '&clipboard=1&dpi=64';
            guac.connect(conn_string); // connect to the guacamole instance

            // Disconnect on close
            window.onunload = function() {
                guac.disconnect();
            }


            // RDP server file/folder download.
            // The official jsp client is a separate back-end API interface for transmission, and the blob is empty "4.blob,1.0,0.;".
            // Here we use ws client blob to transfer data, no need to build another back-end API interface, and the file blob will be automatically removed from the video.
            // The official does not use the blob method, it is estimated that the base64 data becomes larger, and more importantly, when the blob is downloaded,
            // Only when the file is completely transferred to the client end, will download/save pop up,
            // If you are downloading a large file, you need to transfer it to the bastion machine first (with an interface progress bar),
            // From the bastion machine to the client/(base64), the time is longer (no interface interaction, you need to increase it by yourself),
            // Causes the client to easily think that there is no response/repeat operation!
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
            
            guac.onfile = function(stream, mimetype, filename){
                // If downloading a folder, download multiple files for multiple calls to the current function
                stream.sendAck('OK', Guacamole.Status.Code.SUCCESS);  // 告知收到流
                // will read the blob data from the stream
                reader = new Guacamole.BlobReader(stream, mimetype);
                // (Fortress machine ==> client) Downloading, add interface prompts
                var timeout = setTimeout(
                    'loading("uploading ('+filename+') to VM....")',
                    2000
                );
                reader.onend = function() {
                    // end of blob read
                    clearTimeout(timeout);
                    swal.close(); //The client has been downloaded, close the interface prompt
                    var blob_data = reader.getBlob();  // file data (blob type)
                    saveAs(blob_data, filename);  // Save blobs as files with FileSaver.js
                };
            }

            // Drag and upload the file to the RDP server \\tsclient\mapped disk\
            document.ondragover = function(event){
                // Drag and drop files to the window to block, so that the browser does not prompt to open/download
                return false;
            };
            
            document.ondrop = function(event){
                // Drag and download the client file to the bastion machine (the virtual disk configured by GUACD['drive_path'] in conf.py)

                for (i=0; i<event.dataTransfer.files.length; i++) {
                    var file = event.dataTransfer.files[i]
                    // console.log(file);
                    var stream = guac.createFileStream(file.type, file.name);
                    var writer = new Guacamole.BlobWriter(stream);
                    
                    // When uploading large files, add a prompt
                    var timeout = setTimeout(
                        swal({ 
                            title: "uploading '" + file.name + "' to the VM",
                            type: 'info',
                            position: 'top',
                            timer: 20000,
                            toast: true,
                            showConfirmButton: false
                        }),
                        2000
                    );
                    writer.sendBlob(new Blob([file]));
                    writer.oncomplete = function() {
                        // end of blob write
                        writer.sendEnd();
                        clearTimeout(timeout);
                        swal({ 
                            title: "Successfully uploaded '" + file.name + "' on the VM",
                            type: 'info',
                            position: 'top',
                            timer: 4000,
                            toast: true,
                            showConfirmButton: false
                        })
                    };
                }

                // Drag and drop files to the window to block, so that the browser does not prompt to open/download
                return false;

            };

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
                    };

                }

                // Otherwise read the clipboard data as a Blob
                else {
                    reader = new Guacamole.BlobReader(stream, mimetype);
                    reader.onend = function() {
                        // Copy the blob data (usually a picture) to the client clipboard
                        var blob_data = reader.getBlob();
                        console.log(blob_data);
                        // Chrome only triggers the current function when text is copied, others do not trigger, for unknown reasons.
                    };
                }

            };


            var ws_conning = false;
            function reconnect(title){
                if (! title) {title='Connection Terminated!'}
                document.body.style.cursor = 'pointer';
                swal({
                    title: title,
                    text: "Someone has either logged on, or the connection has been terminated. Would you like to reconnect?",
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

            //state change
            tunnel.onstatechange = function(state){
                //var STATE_CONNECTED     = 1;
                //var STATE_DISCONNECTED  = 2;
                if (state == 1){
                    ws_conning = true;
                } else if (ws_conning && state == 4){
                    reconnect(title='Connection Disconnecting!');
                } else if (ws_conning && state == 5) {
                    reconnect(title='Connection Closed!');
                }

            }
            
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
                    console.log(guac.getDisplay.size);
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
                    var data = '';
                    swal({
                        title: 'clipboard',
                        input: 'textarea',
                        confirmButtonText: 'Copy To Remote Clipboard',
                        inputValue: data,
                    }).then(function(result){
                        if (result.value){
                            // Send data to the RDP clipboard
                            var data = result.value;
                            if (data != undefined){
                                var stream = guac.createClipboardStream("text/plain");
                                var writer = new Guacamole.StringWriter(stream);
                                writer.sendText(data);
                                writer.sendEnd();
                            }
                        }else if (result.dismiss === swal.DismissReason.cancel) {
                            console.log(result.value);
                        }
                    })
                }
            );

        </script>
    </body>
</html>
<?php } ?>