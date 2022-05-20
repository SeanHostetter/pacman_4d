<!DOCTYPE html>
<html lang="en">
	<head>
		<title>CAP MAN</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
		<link type="text/css" rel="stylesheet" href="main.css">
	</head>
	<body>
		
		<!--Source code by Temdog007-->
		<!-- <div id="info">
			<a href="https://threejs.org" target="_blank" rel="noopener">three.js</a> Click on a sphere to toggle bloom<br>By <a href="http://github.com/Temdog007" target="_blank" rel="noopener">Temdog007</a>
		</div> -->
		<script src="./jsm/perlin.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
		<script src="./jsm/seedrandom.js">
			Math.seedrandom(50000);
		</script>

		<script type="x-shader/x-vertex" id="vertexshader">

			varying vec2 vUv;

			void main() {

				vUv = uv;

				gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

			}

		</script>

		<script type="x-shader/x-fragment" id="fragmentshader">

			uniform sampler2D baseTexture;
			uniform sampler2D bloomTexture;

			varying vec2 vUv;

			void main() {

				gl_FragColor = ( texture2D( baseTexture, vUv ) + vec4( 1.0 ) * texture2D( bloomTexture, vUv ) );

			}

		</script>

		<!-- Import maps polyfill -->
		<!-- Remove this when import maps will be widely supported -->
		<script async src="https://unpkg.com/es-module-shims@1.3.6/dist/es-module-shims.js"></script>
		<script src="./jsm/src/ace.js" type="text/javascript" charset="utf-8"></script>
		<script src="./jsm/src/mode-c_cpp.js" type="text/javascript"></script>

		<script type="importmap">
			{
				"imports": {
					"three": "./node_modules/three/build/three.module.js"
				}
			}
		</script>

		<script type="module">

			var pn = new Perlin(420);
			var squaresList = [];
			var dotList = [];
			var masterSeed = 5000;
			var gridSize = 20;
			var gridDensity = 0.5;
			//var grid = genGridRandom(gridSize);
			var placeHolderText = "[[0, 0, 0, 0, 1],"
							  + "\n [0, 1, 0, 1, 0],"
							  + "\n [1, 0, 0, 1, 1],"
							  + "\n [0, 1, 0, 0, 0],"
							  + "\n [1, 0, 1, 0, 0]]";
			var placeHolderText2 = "[[0, 0], [1, 0], [2, 0], [2, 1], [2, 2], [2, 3], [3, 3], [3, 4], [4, 4]]";
			var grid = genGridRandom(gridSize);
			var path;
			var panelList;
			var genType = "RANDOM";
			var place = "HOLD";

			import * as THREE from 'three';
			import { Clock } from 'three';

			import { GUI } from './jsm/libs/lil-gui.module.min.js';

			import { OrbitControls } from './jsm/controls/OrbitControls.js';
			import { EffectComposer } from './jsm/postprocessing/EffectComposer.js';
			import { RenderPass } from './jsm/postprocessing/RenderPass.js';
			import { ShaderPass } from './jsm/postprocessing/ShaderPass.js';
			import { PixelShader } from './jsm/shaders/PixelShader.js';

			const ENTIRE_SCENE = 0;

			let pixelPass, paramsPix;
			function updateGUI() {

				pixelPass.uniforms[ 'pixelSize' ].value = params.pixelSize;

			}

			paramsPix = {
					pixelSize: 16,
					postprocessing: true
			};

			const params = {
				gridSize: 20,
				seed: 50000,
				gridDensity: 0.5,
				gridAlgorithm: 'RANDOM'
			};

			const darkMaterial = new THREE.MeshBasicMaterial( { color: 'black' } );
			const materials = {};

			const renderer = new THREE.WebGLRenderer( { antialias: true } );
			renderer.setPixelRatio( window.devicePixelRatio );
			renderer.setSize( window.innerWidth, window.innerHeight );
			renderer.toneMapping = THREE.ReinhardToneMapping;
			document.body.appendChild( renderer.domElement );

			const scene = new THREE.Scene();

			const camera = new THREE.PerspectiveCamera( 40, window.innerWidth / window.innerHeight, 1, 1000 );
			camera.position.set( 0, gridSize * 2, 0 );
			camera.setFocalLength(30)
			camera.lookAt( 0, 0, 0 );
			var cameraTarget = new THREE.Vector3(0, gridSize * 2.5, 0);
			var focalTarget = 30;

			const controls = new OrbitControls( camera, renderer.domElement );
			controls.maxPolarAngle = Math.PI * 0.5;
			controls.minDistance = 1;
			controls.maxDistance = 1000;
			controls.addEventListener( 'change', render );

			scene.add( new THREE.AmbientLight( 0x404040 ) );

			const renderScene = new RenderPass( scene, camera );

			// const bloomPass = new UnrealBloomPass( new THREE.Vector2( window.innerWidth, window.innerHeight ), 1.5, 0.4, 0.85 );
			// bloomPass.threshold = params.bloomThreshold;
			// bloomPass.strength = params.bloomStrength;
			// bloomPass.radius = params.bloomRadius;

			const bloomComposer = new EffectComposer( renderer );
			bloomComposer.renderToScreen = false;
			bloomComposer.addPass( renderScene );

			/*-----------------------TURNING OFF BLOOM FOR PERFORMANCE-----------------------*/
			// bloomComposer.addPass( bloomPass );

			const finalPass = new ShaderPass(
				new THREE.ShaderMaterial( {
					uniforms: {
						baseTexture: { value: null },
						bloomTexture: { value: bloomComposer.renderTarget2.texture }
					},
					vertexShader: document.getElementById( 'vertexshader' ).textContent,
					fragmentShader: document.getElementById( 'fragmentshader' ).textContent,
					defines: {}
				} ), 'baseTexture'
			);
			finalPass.needsSwap = true;

			const finalComposer = new EffectComposer( renderer );
			finalComposer.addPass( renderScene );
			finalComposer.addPass( finalPass );

			const raycaster = new THREE.Raycaster();

			//const mouse = new THREE.Vector2();
			var projector, mouse = {
				x: 0,
				y: 0
			},
			INTERSECTED;

			window.addEventListener( 'pointerdown', onPointerDown );

			var gui = new GUI();
			renderer.toneMappingExposure = Math.pow( 1.6, 4.0 );

			animate();

			const folder1 = gui.addFolder( 'Algorithm Settings' );
			folder1.add( params, 'gridAlgorithm', [ 'BFS', 'DFS', 'RANDOM' ] ).onChange( function ( value ) {

				genType = value;
			} );

			const folder2 = gui.addFolder( 'Grid Settings' );

			folder2.add( params, 'gridSize', 10, 50 ).onChange( function ( value ) {
				gridSize = parseInt(value);
				render();
			} );
			folder2.add( params, 'gridDensity', 0.0, 1.0 ).onChange( function ( value ) {
				gridDensity = value;
				render();
			} );
			folder2.add( params, 'seed', 0, 100000 ).onChange( function ( value ) {
				masterSeed = value;
				render();
			} );

			var obj = {
				add: function() {
					switch (genType){
						case "BFS": grid = genGridRandom(gridSize);
							break;
						case "DFS": grid = genGridRandom(gridSize);
							break;
						case "RANDOM": grid = genGridRandom(gridSize);
							break;
					}
					genMap();
					cameraTarget.set(0, gridSize * 2.5, 0);
				}
			};
			folder2.add(obj, "add").name("regenerate");

			//------------------------------------------------------------------
			const textParams = {
				custGrid: "placeholder"
			}
			
			var textGui = new GUI({ autoPlace: true, top: 2, left: 2 });
			textGui.domElement.id = 'gui2';
			//SET-UP-ACE-EDITOR-----------------------------------------------------------------------------
			function loadGridEditor(){
				var editor = ace.edit("editor");
				editor.setTheme("ace/theme/kuroir");

				var CppMode = require("ace/mode/c_cpp").Mode;
				editor.getSession().setMode(new CppMode());
				return editor;
			}

			const gridFolder = textGui.addFolder( 'Custom Grid' );
			//----------------------------------------------------------------------------------------------
			gridFolder.add(textParams, "custGrid").onFinishChange(function (value) {});
			var gridCodeObj = {
				add: function() {
					//grid = parseGrid(document.getElementById("gridCodeText").value);
					var gridCode = document.getElementById("pathText").value
						<?php 
							$path = 'C:/xampp/htdocs/user/execute.php';
							exec($path, $output,$return);
							var_dump($return);
							echo "hi"."<br>";
							echo "end";
						?>
					}
			};
			gridFolder.add(gridCodeObj, "add").name("Generate Grid");
			//format text gui
			document.getElementById("lil-gui-name-6").outerHTML = "";

			waitForElm('[aria-labelledby="lil-gui-name-6"]').then((elm) => {
				
				elm.outerHTML = '<div id="editor" style="height: 400px; width: 100%;"></div>';
				var editor = loadGridEditor();
			});
			//-----------------------------------------------------------------------------SET-UP-ACE-EDITOR

			gridFolder.add(textParams, "custGrid").onFinishChange(function (value) {});
			var txtObj = {
				add: function() {
						grid = parseGrid(document.getElementById("gridText").value);
						genMap();
						cameraTarget.set(0, gridSize * 2.5, 0);
				}
			};
			gridFolder.add(txtObj, "add").name("Load Grid");
			//format text gui
			document.getElementById("lil-gui-name-8").outerHTML = "";

			waitForElm('[aria-labelledby="lil-gui-name-8"]').then((elm) => {
				elm.outerHTML = "<textarea id='gridText' style='height: 100px; width: 100%; background-color: rgb(32,32,32); color: white;'>" + placeHolderText + "</textarea>";
			});

			//------------------------------------------------------------------
			const pathFolder = textGui.addFolder( 'Custom Path' );
			pathFolder.add(textParams, "custGrid").onFinishChange(function (value) {});

			var txtObj2 = {
				add: function() {
					path = parsePath(document.getElementById("pathText").value);
					genPath();
				}
			};
			pathFolder.add(txtObj2, "add").name("Load Path");
			//format text gui
			document.getElementById("lil-gui-name-10").outerHTML = "";
			waitForElm('[aria-labelledby="lil-gui-name-10"]').then((elm) => {
				elm.outerHTML = "<textarea id='pathText' style='height: 50px; width: 100%; background-color: rgb(32,32,32); color: white;'>" + placeHolderText2 + "</textarea>";
			});
			//------------------------------------------------------------------
			function waitForElm(selector) {
				return new Promise(resolve => {
					if (document.querySelector(selector)) {
						return resolve(document.querySelector(selector));
					}

					const observer = new MutationObserver(mutations => {
						if (document.querySelector(selector)) {
							resolve(document.querySelector(selector));
							observer.disconnect();
						}
					});

					observer.observe(document.body, {
						childList: true,
						subtree: true
					});
				});
			}

			function codeAPI(){
				$.ajax({
					url: 'https://api.wit.ai/message?v=20140826&q=',
					beforeSend: function(xhr) {
						xhr.setRequestHeader("Authorization", "Bearer 6QXNMEMFHNY4FJ5ELNFMP5KRW52WFXN5")
					}, success: function(data){
						alert(data);
						//process the JSON data etc
					}
				})
			}



			//FUNCTION TO GET CONTENTS OF ACE EDITOR--------------------------------------------------------
			function executeCode(){
				var code = editor.getSession().getValue();
			}

			setupScene();

			//PARSE-FUNCTIONS--------------------------------------------------------------------------
			function parseGrid(gridStr){
				gridStr = gridStr.replace(/(\r\n|\n|\r)/gm, "");
				var arr = $.parseJSON(gridStr);
				gridSize = arr.length;

				return arr;
			}

			function parsePath(pathStr){
				var arr = $.parseJSON(pathStr);

				return arr;
			}
			//--------------------------------------------------------------------------PARSE-FUNCTIONS

			//STAR CLICKED---------------------------------------------------------------------
			function onPointerDown( event ) {

				mouse.x = ( event.clientX / window.innerWidth ) * 2 - 1;
				mouse.y = - ( event.clientY / window.innerHeight ) * 2 + 1;

				raycaster.setFromCamera( mouse, camera );
				const intersects = raycaster.intersectObjects( scene.children, false );


				//WHEN STAR IS CLICKED
				if ( intersects.length > 0 ) {

					const object = intersects[ 0 ].object;

					if(object.name.includes("pacman")){
						//controls.object.position.set(object.position.x+10, object.position.y+10, object.position.z+10);
						controls.target = new THREE.Vector3(object.position.x, object.position.y, object.position.z);

						cameraTarget.set(object.position.x+3, object.position.y+3, object.position.z+3);

						render();
					} else {
						cameraTarget.set(0, gridSize * 2.5, 0);
					}
				}
			}
			//---------------------------------------------------------------------STAR CLICKED

			//hover event-------------------------------------------------------
			document.addEventListener('mousemove', onDocumentMouseMove, false);

			function onDocumentMouseMove(event) {
				// the following line would stop any other event handler from firing
				// (such as the mouse's TrackballControls)
				// event.preventDefault();

				// update the mouse variable
				mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
				mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
			}

			//-------------------------------------------------------hover event

			//GAME LOOP-----------------------------------------------------------------
			function animate() {

				camera.position.lerp(cameraTarget, 0.1);
				requestAnimationFrame( animate );
				controls.update();

				render();
				//stats.update();
			}
			//-----------------------------------------------------------------GAME LOOP

			function removeEntity(object) {
				var selectedObject = scene.getObjectByName(object.name);
				scene.remove( selectedObject );
			}
			function removeEntities(object){
				for (let i = 0; i < object.length; i++) {
					removeEntity(object[i]);
				}
				object = [];
			}

			window.onresize = function () {

				const width = window.innerWidth;
				const height = window.innerHeight;

				camera.aspect = width / height;
				camera.updateProjectionMatrix();

				renderer.setSize( width, height );

				bloomComposer.setSize( width, height );
				finalComposer.setSize( width, height );

				render();

			};

			function hypot(a, b){
				return Math.sqrt((Math.pow(a.position.x, 2) - Math.pow(a.position.z, 2)) + (Math.pow(a.position.x, 2) - Math.pow(a.position.z, 2)));
			}

			function compare( a, b ) {
				if ( a.position.x < b.position.x){
					return -1;
				} else {
					return 1;
				}
				return 0;
			}

			function genPath(){
				const geometry = new THREE.CircleGeometry( 5, 32 );

				for(let i = 0; i < path.length; i++){
					const color = new THREE.Color();
					color.setHSL( 1, 1, 1 );

					const material = new THREE.MeshBasicMaterial( { color: color } );
					const dot = new THREE.Mesh( geometry, material );

					dot.position.x = path[i][0]-2; - (gridSize-1)/2;
					dot.position.y = 0;
					dot.position.z = path[i][1]-2; - (gridSize-1)/2;

					dot.rotation.x = 3*Math.PI/2;
					
					dot.scale.setScalar( 0.03 );

					//add star to array
					dotList.push(dot);
					dotList[dotList.length-1].name = "dot_" + i;

					//add star to scene
					scene.add( dot );

					
				}
			}

			function checkSquare(tempGrid, i, j, depth, origin){

				console.log(origin[0]);
				console.log(origin[1]);

				if(Math.min(i, j) < 1 || Math.max(i, j) >= gridSize-2){
					return false;
				}
				if(depth > gridSize * gridSize * gridDensity){
					return false;
				}

				if(!tempGrid[i+1][j] && (i+1 == origin[0] && j == origin[1])){
					return false;
				}
				if(!tempGrid[i][j+1] && (i == origin[0] && j+1 == origin[1])){
					return false;
				}
				if(!tempGrid[i-1][j] && (i-1 == origin[0] && j == origin[1])){
					return false;
				}
				if(!tempGrid[i][j-1] && (i == origin[0] && j-1 == origin[1])){
					return false;
				}
				return true;
			}

			function DFS(tempGrid, i, j, depth, origin){
				if(!checkSquare(tempGrid, i, j, depth, origin)){
					return;
				}

				tempGrid[i][j] = false;

				origin[0] = i;
				origin[1] = j;

				var dirRand = Math.random();
				if (dirRand < 0.25) {
					DFS(tempGrid, i+1, j, depth, origin);
					DFS(tempGrid, i, j+1, depth, origin);
					DFS(tempGrid, i-1, j, depth, origin);
				} else if (dirRand < 0.5) {
					DFS(tempGrid, i, j+1, depth, origin);
					DFS(tempGrid, i-1, j, depth, origin);
					DFS(tempGrid, i, j-1, depth, origin);
				} else if (dirRand < 0.75) {
					DFS(tempGrid, i-1, j, depth, origin);
					DFS(tempGrid, i, j-1, depth, origin);
					DFS(tempGrid, i+1, j, depth, origin);
				} else {
					DFS(tempGrid, i, j-1, depth, origin);
					DFS(tempGrid, i+1, j, depth, origin);
					DFS(tempGrid, i, j+1, depth, origin);
				}

				return;
			}

			function genGridDFS(size){
				var x = new Array(size);

				for (var i = 0; i < size; i++) {
					x[i] = new Array(size);
				}

				for(let i = 0; i < size; i++){
					for(let j = 0; j < size; j++){
						x[i][j] = true;
					} 
				}

				//CHANGE-SEED---------------------------------
				Math.seedrandom(masterSeed);
				//---------------------------------CHANGE-SEED
				Math.seedrandom(masterSeed);
				var startX =  parseInt((Math.random() * gridSize-2)+1);
				var startY = parseInt((Math.random() * gridSize-2)+1);
				var depth = 0;

				var origin = new Array(2);
				origin[0] = startX;
				origin[1] = startY;


				DFS(x, startX, startY, depth, origin);

				return x;
			}

			function genGridRandom(size){
				var x = new Array(size);

				for (var i = 0; i < size; i++) {
					x[i] = new Array(size);
				}

				//CHANGE-SEED---------------------------------
				Math.seedrandom(masterSeed);
				//---------------------------------CHANGE-SEED

				for(let i = 0; i < size; i++){
					for(let j = 0; j < size; j++){
						if(Math.random() < gridDensity){
							x[i][j] = 1;
						} else {
							x[i][j] = 0;
						}
					} 
				}
				return x;
			}

			function clearMap(){
				while(scene.children.length > 0){ 
					scene.remove(scene.children[0]); 
				}
			}

			function genSquare(i, j, geometry){
				const color = new THREE.Color();
				color.setHSL( 0.5, 0.8, 0.5 );

				const material = new THREE.MeshBasicMaterial( { color: color } );
				const square = new THREE.Mesh( geometry, material );

				square.position.x = j - (gridSize-1)/2;
				square.position.y = 0;
				square.position.z = i - (gridSize-1)/2;

				square.rotation.x = 3*Math.PI/2;
				
				square.scale.setScalar( 1 );

				//add star to array
				squaresList.push(square);
				squaresList[squaresList.length-1].name = "square_" + i + "_" + j;

				//add star to scene
				scene.add( square );
			}

			function genMap(){
				clearMap();

				const geometry = new THREE.PlaneGeometry( 1, 1 );

				for ( let i = 0; i < gridSize+1; i ++ ) {
					genSquare(i-1, -1,          geometry);
					genSquare(i-1, gridSize, geometry);
				}
				for ( let i = 0; i < gridSize+2; i ++ ) {
					genSquare(-1,          i-1, geometry);
					genSquare(gridSize, i-1, geometry);
				}

				for ( let i = 0; i < gridSize; i ++ ) {
					for ( let j = 0; j < gridSize; j ++ ) {
						if(grid[i][j] === 1){
							genSquare(i, j, geometry);
						}
					}
				}
			}

			function setupScene() {

				scene.traverse( disposeMaterial );
				scene.children.length = 0;

				genMap();

				pixelPass = new ShaderPass( PixelShader );
				pixelPass.uniforms[ 'resolution' ].value = new THREE.Vector2( window.innerWidth, window.innerHeight );
				pixelPass.uniforms[ 'resolution' ].value.multiplyScalar( window.devicePixelRatio );
				finalComposer.addPass( pixelPass );

				render();

			}

			function onWindowResize() {

				camera.aspect = window.innerWidth / window.innerHeight;
				camera.updateProjectionMatrix();
				renderer.setSize( window.innerWidth, window.innerHeight );

				pixelPass.uniforms[ 'resolution' ].value.set( window.innerWidth, window.innerHeight ).multiplyScalar( window.devicePixelRatio );

			}

			function disposeMaterial( obj ) {

				if ( obj.material ) {

					obj.material.dispose();

				}
			}

			function render() {
				// render the entire scene
				finalComposer.render();
			}

			function renderBloom( mask ) {

				//camera.layers.set( BLOOM_SCENE );;
				bloomComposer.render();
				camera.layers.set( ENTIRE_SCENE );
			}

			function restoreMaterial( obj ) {

				if ( materials[ obj.uuid ] ) {

					obj.material = materials[ obj.uuid ];
					delete materials[ obj.uuid ];

				}
			}
		</script>

	</body>

</html>
