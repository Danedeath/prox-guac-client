<?php

include './header.php';

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] != true) {
    header("location: ./login/login.php");
    exit;
}

if (empty($running_nodes)) {
    $errorMSG = "No running nodes were discovered!";
    include $root."/extra/error.php";
    die();
}

$all_users     = $loginHanlder->getAllUsers();
$all_vms       = $proxmox->getAllVMs($all_users);
$totlClustRsc  = $proxmox->getClusterResources();
$allClustRsc   = $proxmox->getClusterResc();
$running_vms   = array();
$unknown_vms   = array();


foreach ($all_vms as $vm) {
    if ($vm['status'] == "running") {
        array_push($running_vms, $vm);
    } else if ($vm['status'] == "unknown") {
        $vm['status'] = "stopped";
        array_push($unknown_vms, $vm);
    }     
}

?>
    <div class="row position-absolute main-body text-light">
        <section class="content-header">
            <h2>
                Dashboard
                <small>
                    Proxmox Admin Panel v0.0.1
                </small>
            </h2>
            <ol class="breadcrumb pe-5 pull-right">
                <li>
                    <a href="index.php">
                        <i class="fa fa-dashboard"></i> 
                        Home 
                    </a>
                </li>
                <li class="active"> Dashboard</li>
            </ol>
        </section>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.0.1/chart.min.js" integrity="sha512-tQYZBKe34uzoeOjY9jr3MX7R/mo7n25vnqbnrkskGr4D6YOoPYSpyafUAzQVjV6xAozAqUFIEFsCO4z8mnVBXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.0.1/chart.umd.js" integrity="sha512-gQhCDsnnnUfaRzD8k1L5llCCV6O9HN09zClIzzeJ8OJ9MpGmIlCxm+pdCkqTwqJ4JcjbojFr79rl2F1mzcoLMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.0.1/helpers.js" integrity="sha512-wGzztNvoC00n0GzfyGz17CaVJ8rihq5cFnoJDJvF2Ul3wy1K2UsnXHutmZcjIHQOTGjeZTuAL3PH9GwjwZ7aHA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <div class="row pt-2 ps-3 ms-5 col-md-8">
            <div class="col-xs-6 col-sm-3 text-center pe-5 ms-5">
                <canvas id="nodeChart" width="192px" height="192px"></canvas>
                <span class="text-muted">Online Nodes</span>
            </div>
            <div class="col-xs-6 col-sm-3 text-center pe-5 ms-3">
                <canvas id="osChart" width="192px" height="192px"></canvas>
                <span class="text-muted mt-2">Operating Systems</span>
            </div>
            <div class="col-xs-6 col-sm-3 text-center pe-5 ms-3">
                <canvas id="vmChart" width="192px" height="192px"></canvas>
                <span class="text-muted">Total VMs</span>
            </div>
            <div class="col-xs-6 col-sm-3 text-center pe-5 ms-3">
                <canvas id="memChart" width="192px" height="192px"></canvas>
                <span class="text-muted">Memory Usage</span>
            </div>
            <div class="col-xs-6 col-sm-3 text-center pe-5 ms-3">
                <canvas id="cpuChart" width="192px" height="192px"></canvas>
                <span class="text-muted">CPU Usage</span>
            </div>
            <div class="row pt-2 ps-5">
                <div class="box no-padding">
                    <div class="box-header with-border">
                        <h3 class="box-title">Active Machines</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body no-padding">
                        <table id="latestMachines" class="table text-light table-dark">
                            <thead>
                                <tr>
                                    <th>VM Name</th>
                                    <th>IP</th>
                                    <th>OS</th>
                                    <th>CPU</th>
                                    <th>RAM</th>
                                    <th>Storage</th>
                                    <th>Uptime</th>
                                    <th>Status</th>
                                    <th>Node</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $statColor = array('running' => '#009933', 'stopped' => '#bf9000', 'unknown' => '#cc0000');
                                    $osColor   = array('mswindows' => '#009933', 'kali' => '#45818e', 'linux' => '#bf9000', 'missing agent' => '#cc0000');

                                    foreach ($all_vms as $vm) {
                                        echo "<tr>";
                                        echo "<td>".$vm['name']."</td>";
                                        echo "<td>".$vm['conn']."</td>";
                                        echo "<td style='color:".$osColor[$vm['os']]." !important'>".$vm['os']."</td>";
                                        echo "<td>".$vm['cpus']."</td>";
                                        echo "<td>".$vm['maxmem']."</td>";
                                        echo "<td>".$vm['maxdisk']."</td>";
                                        echo "<td>".$vm['uptime']."</td>";
                                        echo "<td style='color:".$statColor[$vm['status']]." !important'>".$vm['status']."</td>";
                                        echo "<td>".$vm['node']."</td>";
                                        echo "</tr>";

                                    }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 ps-3 pull-right pt-2">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Node Usage</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse">
                            <i class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body no-padding">
                    <table id="nodeUsage" class="table text-light table-dark">
                        <thead>
                            <tr>
                                <th>Node</th>
                                <th>Memory</th>
                                <th>CPU</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allClustRsc as $node) { ?>
                            <tr>
                                <td><?php echo $node->node; ?></td>
                                <td><?php echo round(($node->mem / $node->maxmem ) * 100, 2)."%"; ?></td>
                                <td><?php echo round($node->cpu * 100, 2)."%"; ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>

    var chartData   = [<?php echo (count($running_nodes) < count($nodes)) ? count($running_nodes).",".(count($nodes) - count($running_nodes)) : count($running_nodes) ?>];
    var chartLabels = [<?php echo (count($running_nodes) < count($nodes)) ? "'Online', 'Offline'" : "'Online'" ?>];
    var chartColors = ['#009933', '#cc0000'];
    createDChart("nodeChart", chartData, chartLabels, chartColors, '');

    var chartData   = [<?php

        $active_vms   = count($running_vms);
        $unactive_vms = count($all_vms) - $active_vms;
        $unk_vms      = count($unknown_vms);
        
        echo "".$active_vms.",".$unactive_vms.",".$unk_vms;

    ?>];

    var chartLabels = ['Running', 'Stopped', 'Unknown'];
    var chartColors = ['#009933', '#bf9000', '#cc0000'];
    createDChart("vmChart", chartData, chartLabels, chartColors, '');
    
    var chartData   = [<?php

        $windows = 0;
        $linux   = 0;
        $noagent = 0;
        $kali    = 0;

        foreach ($all_vms as $vm) {
            if (stripos($vm['os'], "mswin") !== false) {
                $windows++;
            } else if (stripos($vm['os'], "agent") !== false) {
                $noagent++;
            } else if (stripos($vm['os'], "kali") !== false) {
                $kali++;
            } else {
                $linux++;
            }
        }

        echo "".$windows.",".$kali.",".$linux.",".$noagent;
    ?>];
    var chartLabels = ['Windows', 'Kali', 'Linux', 'Missing Agents'];
    var chartColors = ['#009933', '#45818e', '#bf9000', '#cc0000'];
    createDChart("osChart", chartData, chartLabels, chartColors, ''); 

    var chartData = [<?php  
         echo $totlClustRsc['mem_usage'].",".(100 - $totlClustRsc['mem_usage']);
    ?>]  ;
    var chartLabels = [ 'Used', 'Free'];
    var chartColors = [  
        <?php echo ($totlClustRsc['mem_usage'] > 75) ? '"#bf9000"': '"#009933"'; ?>, 
          <?php echo ($totlClustRsc['mem_usage'] > 75) ? '"#cc0000"': '"#bf9000"'; ?>
    ]  ;    
    createDChart("memChart", chartData, chartLabels, chartColors, <?php echo '"'.$totlClustRsc['mem_usage'].'%"' ?>);    

    var chartData = [<?php  
         echo $totlClustRsc['cpu_usage'].",".(100 - $totlClustRsc['cpu_usage'] );
    ?>]  ;
    var chartLabels = [ 'Used', 'Free'];
    var chartColors = [  
        <?php echo ($totlClustRsc['cpu_usage'] > 75) ? '"#bf9000"': '"#009933"'; ?>, 
          <?php echo ($totlClustRsc['cpu_usage'] > 75) ? '"#cc0000"': '"#bf9000"'; ?>
    ]  ;   
    createDChart("cpuChart", chartData, chartLabels, chartColors, <?php echo '"'.$totlClustRsc['cpu_usage'].'%"' ?>);
      
    Chart.defaults.color = '#ddd';

    function createDChart(chartID, inData, inLabels, inColors, middleText) { 
        var ctx = document.getElementById(chartID);
        var chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: inLabels,
                datasets: [{
                    data: inData,
                    backgroundColor: inColors,
                    borderWidth: 0
                }],
            },
            options: {
                cutout: '70%',
                responsive: true,
                circumference: 180,
                rotation: -90,
                aspectRatio: 1,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                animation: {
                    duration: 0
                }
            },
            plugins: [{
                id: 'doughnutInteriorText',
                beforeDraw: function(chart) {
                    var width = chart.chartArea.width,
                    height = chart.chartArea.height,
                    ctx = chart.ctx;

                    ctx.restore();
                    var fontSize = (height / 192).toFixed(2);
                    ctx.font = fontSize + "em sans-serif";
                    ctx.textBaseline = "middle";
                    ctx.fillStyle = "#ddd";


                    var text = middleText,
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = Math.floor(height / 1.5);

                    ctx.fillText(text, textX, textY);
                    ctx.save();
                }
            }]
        });
        chart.canvas.parentNode.style.height = '256px';
        chart.canvas.parentNode.style.width  = '256px';
    }
</script>
</html>