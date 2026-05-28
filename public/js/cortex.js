/**
 * Unio Cortex — malha neural 3D avançada + grafo 2D
 * v3: hemisférios cerebrais, tubos sinápticos, impulsos elétricos, shader de partículas
 */
(function (global) {
    'use strict';

    var page = document.getElementById('cortexPage');
    if (!page) return;

    var payload = {};
    try {
        payload = JSON.parse(document.getElementById('cortex-payload').textContent || '{}');
    } catch (e) {
        payload = {};
    }

    var activeDomain = 'all';
    var graphChart = null;
    var prefersReduced = global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isMobile = global.innerWidth < 768;
    var threeCtx = {
        scene: null, camera: null, renderer: null, group: null,
        clusters: [], particles: null, synapses: [], core: null,
        brains: [], tubes: [], signals: [], ripples: [], dendrites: [],
        frame: null, clock: null, mouse: { x: 0, y: 0, tx: 0, ty: 0 },
        hudLabels: [], tipEl: null, host: null, raycaster: null,
        eegCanvas: null, eegCtx: null, eegBuffer: [], pulseScore: 0, pulseStatus: 'idle'
    };

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function hexToRgba(hex, alpha) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (h.length !== 6) return 'rgba(79,127,255,' + alpha + ')';
        return 'rgba(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ',' + alpha + ')';
    }

    function domainColor(id) {
        var domains = payload.domains || [];
        for (var i = 0; i < domains.length; i++) {
            if (domains[i].id === id) return domains[i].color;
        }
        return cssVar('--accent', '#4F7FFF');
    }

    function domainById(id) {
        var domains = payload.domains || [];
        for (var i = 0; i < domains.length; i++) {
            if (domains[i].id === id) return domains[i];
        }
        return null;
    }

    function filteredGraph(domain) {
        var graph = payload.graph || { nodes: [], links: [], categories: [] };
        if (domain === 'all') return graph;
        var nodeIds = {};
        var nodes = graph.nodes.filter(function (n) {
            if (n.domain === domain || n.level === 'core') {
                nodeIds[n.id] = true;
                return true;
            }
            return false;
        });
        var links = graph.links.filter(function (l) {
            return nodeIds[l.source] && nodeIds[l.target];
        });
        return { nodes: nodes, links: links, categories: graph.categories };
    }

    function initGraph(domain) {
        var host = document.getElementById('cortexGraph');
        if (!host || typeof echarts === 'undefined') return;

        var graph = filteredGraph(domain);
        if (!graphChart) graphChart = echarts.init(host, null, { renderer: 'canvas' });

        var text1 = cssVar('--text-1', '#E8EDF5');
        var text2 = cssVar('--text-2', '#8A96A3');
        var border = cssVar('--border', 'rgba(255,255,255,0.08)');
        var accent = cssVar('--accent', '#4F7FFF');

        graphChart.setOption({
            backgroundColor: 'transparent',
            animationDuration: prefersReduced ? 0 : 1200,
            animationEasingUpdate: 'cubicInOut',
            tooltip: {
                backgroundColor: cssVar('--surface-2', '#1a2030'),
                borderColor: border,
                textStyle: { color: text1, fontSize: 12 },
                formatter: function (p) {
                    if (p.dataType === 'edge') {
                        return (p.data.source || '') + ' → ' + (p.data.target || '') +
                            (p.data.value ? '<br/>Fluxo: ' + p.data.value : '');
                    }
                    var d = p.data || {};
                    return '<strong>' + (d.name || '') + '</strong>' +
                        (d.value ? '<br/>Peso: ' + d.value : '');
                }
            },
            legend: {
                data: (graph.categories || []).map(function (c) { return c.name; }),
                bottom: 0,
                textStyle: { color: text2, fontSize: 11 }
            },
            series: [{
                type: 'graph',
                layout: 'force',
                roam: true,
                draggable: true,
                focusNodeAdjacency: true,
                edgeSymbol: ['circle', 'arrow'],
                edgeSymbolSize: [4, 10],
                categories: (graph.categories || []).map(function (c) {
                    return { name: c.name, itemStyle: { color: c.color } };
                }),
                label: {
                    show: true,
                    position: 'right',
                    color: text1,
                    fontSize: 11,
                    fontWeight: 600,
                    formatter: function (p) {
                        var name = p.data.name || '';
                        return name.length > 24 ? name.slice(0, 22) + '…' : name;
                    }
                },
                lineStyle: {
                    color: 'source',
                    curveness: 0.28,
                    opacity: 0.62,
                    width: 1.5
                },
                emphasis: {
                    focus: 'adjacency',
                    scale: true,
                    lineStyle: { width: 3.5, opacity: 0.92, shadowBlur: 8, shadowColor: accent }
                },
                force: {
                    repulsion: domain === 'all' ? 420 : 320,
                    edgeLength: [80, 180],
                    gravity: 0.06,
                    friction: 0.28,
                    layoutAnimation: !prefersReduced
                },
                data: graph.nodes.map(function (n) {
                    var cat = graph.categories[n.category] || {};
                    var col = cat.color || domainColor(n.domain);
                    return {
                        id: n.id,
                        name: n.name,
                        value: n.value,
                        symbolSize: n.symbolSize || 20,
                        category: n.category,
                        itemStyle: {
                            color: {
                                type: 'radial', x: 0.4, y: 0.35, r: 0.9,
                                colorStops: [
                                    { offset: 0, color: col },
                                    { offset: 1, color: hexToRgba(col, 0.45) }
                                ]
                            },
                            borderColor: cssVar('--surface', '#161C24'),
                            borderWidth: 2,
                            shadowBlur: n.level === 'domain' ? 16 : 8,
                            shadowColor: hexToRgba(col, 0.45)
                        }
                    };
                }),
                links: graph.links.map(function (l) {
                    return {
                        source: l.source,
                        target: l.target,
                        value: l.value || 1,
                        lineStyle: { width: Math.max(1, Math.min(6, (l.value || 1) * 0.8)) }
                    };
                })
            }]
        }, true);

        graphChart.off('click');
        graphChart.on('click', function (params) {
            if (params.dataType !== 'node') return;
            var node = findNode(params.data.id);
            if (node && node.url) global.location.href = node.url;
        });
    }

    function findNode(id) {
        var nodes = (payload.graph && payload.graph.nodes) || [];
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].id === id) return nodes[i];
        }
        return null;
    }

    /* ─── Neural 3D engine v3 ─── */

    function fibonacciSphere(i, n, radius) {
        var phi = Math.acos(-1 + (2 * (i + 0.5)) / n);
        var theta = Math.PI * (1 + Math.sqrt(5)) * i;
        return new THREE.Vector3(
            radius * Math.cos(theta) * Math.sin(phi),
            radius * Math.sin(theta) * Math.sin(phi),
            radius * Math.cos(phi)
        );
    }

    function deformBrainGeometry(geo, baseRadius, t, intensity) {
        var base = geo.userData.basePositions;
        var pos = geo.attributes.position.array;
        var i;
        for (i = 0; i < pos.length; i += 3) {
            var bx = base[i];
            var by = base[i + 1];
            var bz = base[i + 2];
            var len = Math.sqrt(bx * bx + by * by + bz * bz) || 1;
            var nx = bx / len;
            var ny = by / len;
            var nz = bz / len;
            var groove = Math.sin(ny * 9 + t * 0.9) * 0.045
                + Math.sin(nx * 14 - t * 0.6) * 0.032
                + Math.sin(nz * 11 + t * 0.4) * 0.028
                + Math.sin((nx + ny) * 18 + t) * 0.018;
            var sulcus = Math.pow(Math.abs(Math.sin(nx * 6 + ny * 4)), 2) * 0.025;
            var r = baseRadius + (groove + sulcus) * intensity;
            pos[i] = nx * r;
            pos[i + 1] = ny * r;
            pos[i + 2] = nz * r;
        }
        geo.attributes.position.needsUpdate = true;
        geo.computeVertexNormals();
    }

    function createBrainHemisphere(side, radius, color) {
        var segments = isMobile ? 24 : 36;
        var geo = new THREE.SphereGeometry(radius, segments, segments);
        geo.userData.basePositions = geo.attributes.position.array.slice();
        deformBrainGeometry(geo, radius, 0, 1);

        var group = new THREE.Group();
        group.position.x = side * 0.22;
        group.scale.set(1.02, 0.94, 1.14);

        var solid = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({
            color: color,
            transparent: true,
            opacity: 0.04,
            metalness: 0.35,
            roughness: 0.82,
            side: THREE.DoubleSide,
            depthWrite: false
        }));
        group.add(solid);

        var wireGeo = geo.clone();
        wireGeo.userData.basePositions = geo.userData.basePositions.slice();
        var wire = new THREE.Mesh(wireGeo, new THREE.MeshBasicMaterial({
            color: color,
            wireframe: true,
            transparent: true,
            opacity: 0.09
        }));
        group.add(wire);

        var innerGlow = new THREE.Mesh(
            new THREE.SphereGeometry(radius * 0.88, 16, 16),
            new THREE.MeshBasicMaterial({
                color: color,
                transparent: true,
                opacity: 0.03,
                blending: THREE.AdditiveBlending,
                depthWrite: false
            })
        );
        group.add(innerGlow);

        return { group: group, solid: solid, wire: wire, wireGeo: wireGeo, radius: radius, side: side };
    }

    function createShaderParticleField(count, radius) {
        var positions = new Float32Array(count * 3);
        var phases = new Float32Array(count);
        var speeds = new Float32Array(count);
        var sizes = new Float32Array(count);
        var accent = new THREE.Color(cssVar('--accent', '#4F7FFF'));
        var i;

        for (i = 0; i < count; i++) {
            var r = radius * (0.45 + Math.random() * 0.75);
            var v = fibonacciSphere(i, count, r);
            positions[i * 3] = v.x;
            positions[i * 3 + 1] = v.y;
            positions[i * 3 + 2] = v.z;
            phases[i] = Math.random() * Math.PI * 2;
            speeds[i] = 0.3 + Math.random() * 1.4;
            sizes[i] = 0.015 + Math.random() * 0.035;
        }

        var geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geo.setAttribute('aPhase', new THREE.BufferAttribute(phases, 1));
        geo.setAttribute('aSpeed', new THREE.BufferAttribute(speeds, 1));
        geo.setAttribute('aSize', new THREE.BufferAttribute(sizes, 1));

        var mat = new THREE.ShaderMaterial({
            uniforms: {
                uTime: { value: 0 },
                uColor: { value: accent },
                uOpacity: { value: 0.75 }
            },
            vertexShader: [
                'attribute float aPhase;',
                'attribute float aSpeed;',
                'attribute float aSize;',
                'uniform float uTime;',
                'varying float vAlpha;',
                'void main() {',
                '  vec3 p = position;',
                '  float w = sin(uTime * aSpeed + aPhase) * 0.04;',
                '  p += normalize(position + 0.001) * w;',
                '  p.x += sin(uTime * 0.3 + aPhase) * 0.02;',
                '  p.y += cos(uTime * 0.25 + aPhase * 1.3) * 0.02;',
                '  vAlpha = 0.35 + 0.65 * (0.5 + 0.5 * sin(uTime * aSpeed * 1.5 + aPhase));',
                '  vec4 mv = modelViewMatrix * vec4(p, 1.0);',
                '  gl_PointSize = aSize * (280.0 / -mv.z);',
                '  gl_Position = projectionMatrix * mv;',
                '}'
            ].join('\n'),
            fragmentShader: [
                'uniform vec3 uColor;',
                'uniform float uOpacity;',
                'varying float vAlpha;',
                'void main() {',
                '  vec2 c = gl_PointCoord - 0.5;',
                '  float d = length(c);',
                '  if (d > 0.5) discard;',
                '  float glow = smoothstep(0.5, 0.0, d);',
                '  gl_FragColor = vec4(uColor, glow * vAlpha * uOpacity);',
                '}'
            ].join('\n'),
            transparent: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        return new THREE.Points(geo, mat);
    }

    function createPlexusLines(count, radius) {
        var points = [];
        var verts = [];
        var i;
        for (i = 0; i < count; i++) {
            verts.push(fibonacciSphere(i, count, radius * (0.65 + Math.random() * 0.4)));
        }
        for (i = 0; i < count; i++) {
            var j = (i + 1 + Math.floor(Math.random() * 5)) % count;
            var k = (i + 7 + Math.floor(Math.random() * 8)) % count;
            points.push(verts[i], verts[j]);
            if (i % 2 === 0) points.push(verts[i], verts[k]);
            if (i % 5 === 0) points.push(verts[j], verts[k]);
        }
        var geo = new THREE.BufferGeometry().setFromPoints(points);
        var mat = new THREE.LineBasicMaterial({
            color: cssVar('--accent', '#4F7FFF'),
            transparent: true,
            opacity: 0.06,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });
        return new THREE.LineSegments(geo, mat);
    }

    function createNeuralShells(group) {
        var accent = cssVar('--accent', '#4F7FFF');
        [
            { r: 1.62, detail: 5, op: 0.09, speed: 0.8 },
            { r: 1.35, detail: 4, op: 0.06, speed: -0.55 },
            { r: 1.08, detail: 3, op: 0.04, speed: 1.1 }
        ].forEach(function (cfg, idx) {
            var mesh = new THREE.Mesh(
                new THREE.IcosahedronGeometry(cfg.r, cfg.detail),
                new THREE.MeshBasicMaterial({
                    color: accent,
                    wireframe: true,
                    transparent: true,
                    opacity: cfg.op
                })
            );
            mesh.userData.shellSpeed = cfg.speed;
            mesh.userData.shellIdx = idx;
            group.add(mesh);
        });
    }

    function createCoreNucleus(pulseScore, pulseStatus) {
        var group = new THREE.Group();
        var col = pulseStatus === 'healthy' ? '#22c55e'
            : pulseStatus === 'attention' ? '#f59e0b'
                : pulseStatus === 'critical' ? '#ef4444' : cssVar('--accent', '#4F7FFF');
        var color = new THREE.Color(col);
        var core = new THREE.Mesh(
            new THREE.SphereGeometry(0.2, 40, 40),
            new THREE.MeshStandardMaterial({
                color: color,
                emissive: color,
                emissiveIntensity: 1.1,
                metalness: 0.7,
                roughness: 0.15
            })
        );
        group.add(core);

        var glow = new THREE.Mesh(
            new THREE.SphereGeometry(0.38, 24, 24),
            new THREE.MeshBasicMaterial({
                color: color,
                transparent: true,
                opacity: 0.22,
                blending: THREE.AdditiveBlending,
                depthWrite: false
            })
        );
        group.add(glow);

        var glow2 = new THREE.Mesh(
            new THREE.SphereGeometry(0.55, 16, 16),
            new THREE.MeshBasicMaterial({
                color: color,
                transparent: true,
                opacity: 0.08,
                blending: THREE.AdditiveBlending,
                depthWrite: false
            })
        );
        group.add(glow2);

        [0.42, 0.58, 0.74].forEach(function (r, idx) {
            var ring = new THREE.Mesh(
                new THREE.TorusGeometry(r, 0.008 + idx * 0.003, 8, 80),
                new THREE.MeshBasicMaterial({ color: color, transparent: true, opacity: 0.35 - idx * 0.08 })
            );
            ring.rotation.x = Math.PI / 2 + idx * 0.15;
            ring.userData.isOrbitRing = true;
            ring.userData.orbitSpeed = 0.35 + idx * 0.12;
            group.add(ring);
        });

        group.userData.pulseScore = pulseScore;
        return { group: group, core: core, glow: glow, glow2: glow2, color: color };
    }

    function createNeuralTube(from, to, color, domainId) {
        var mid = from.clone().add(to).multiplyScalar(0.5);
        var perp = new THREE.Vector3().crossVectors(from, to).normalize();
        if (perp.lengthSq() < 0.01) perp.set(0, 1, 0);
        var bulge = from.distanceTo(to) * 0.35;
        var c1 = mid.clone().add(perp.clone().multiplyScalar(bulge * 0.6));
        var c2 = mid.clone().add(perp.clone().multiplyScalar(-bulge * 0.4));
        c1.y += bulge * 0.15;
        c2.y -= bulge * 0.1;

        var curve = new THREE.CatmullRomCurve3([
            new THREE.Vector3(0, 0, 0),
            c1,
            c2,
            to.clone()
        ], false, 'catmullrom', 0.35);

        var tubeGeo = new THREE.TubeGeometry(curve, 64, 0.012, 6, false);
        var tubeMat = new THREE.MeshBasicMaterial({
            color: color,
            transparent: true,
            opacity: 0.35,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });
        var tube = new THREE.Mesh(tubeGeo, tubeMat);

        var wirePoints = curve.getPoints(48);
        var wireGeo = new THREE.BufferGeometry().setFromPoints(wirePoints);
        var wire = new THREE.Line(wireGeo, new THREE.LineBasicMaterial({
            color: color,
            transparent: true,
            opacity: 0.18,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        }));

        return { curve: curve, tube: tube, wire: wire, domainId: domainId, color: color };
    }

    function createDendriteBranch(origin, direction, depth, color, group) {
        if (depth <= 0) return;
        var len = 0.15 + Math.random() * 0.22;
        var end = origin.clone().add(direction.clone().multiplyScalar(len));
        var geo = new THREE.BufferGeometry().setFromPoints([origin, end]);
        var line = new THREE.Line(geo, new THREE.LineBasicMaterial({
            color: color,
            transparent: true,
            opacity: 0.12 + depth * 0.04,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        }));
        group.add(line);
        threeCtx.dendrites.push(line);

        if (depth > 1 && Math.random() > 0.35) {
            var branchDir = direction.clone();
            branchDir.x += (Math.random() - 0.5) * 0.8;
            branchDir.y += (Math.random() - 0.5) * 0.6;
            branchDir.z += (Math.random() - 0.5) * 0.8;
            branchDir.normalize();
            createDendriteBranch(end, branchDir, depth - 1, color, group);
        }
        if (depth > 1 && Math.random() > 0.5) {
            var branchDir2 = direction.clone();
            branchDir2.x += (Math.random() - 0.5) * 0.9;
            branchDir2.y += (Math.random() - 0.5) * 0.5;
            branchDir2.z += (Math.random() - 0.5) * 0.7;
            branchDir2.normalize();
            createDendriteBranch(end, branchDir2, depth - 1, color, group);
        }
    }

    function createDomainCluster(domain, position) {
        var color = new THREE.Color(domain.color || '#4F7FFF');
        var cluster = new THREE.Group();
        cluster.position.copy(position);
        var activity = Math.min(1, ((domain.score || 50) / 100) * 0.6 + ((domain.entities || 1) / 12) * 0.4);
        cluster.userData = {
            domainId: domain.id,
            label: domain.label,
            score: domain.score || 50,
            entities: domain.entities || 0,
            activity: activity,
            basePos: position.clone()
        };

        var coreSize = 0.08 + Math.min(domain.value || 1, 24) * 0.007;
        var core = new THREE.Mesh(
            new THREE.IcosahedronGeometry(coreSize, 2),
            new THREE.MeshStandardMaterial({
                color: color,
                emissive: color,
                emissiveIntensity: 0.45 + activity * 0.5,
                metalness: 0.6,
                roughness: 0.22,
                flatShading: true
            })
        );
        cluster.add(core);

        var glow = new THREE.Mesh(
            new THREE.SphereGeometry(coreSize * 2.4, 16, 16),
            new THREE.MeshBasicMaterial({
                color: color,
                transparent: true,
                opacity: 0.18 + activity * 0.12,
                blending: THREE.AdditiveBlending,
                depthWrite: false
            })
        );
        cluster.add(glow);

        var ring = new THREE.Mesh(
            new THREE.TorusGeometry(coreSize * 3, 0.005, 6, 56),
            new THREE.MeshBasicMaterial({ color: color, transparent: true, opacity: 0.4 })
        );
        ring.rotation.x = Math.PI / 2;
        cluster.add(ring);

        var satellites = new THREE.Group();
        var satCount = Math.min(Math.max(domain.entities || 1, 3), 12);
        var s;
        for (s = 0; s < satCount; s++) {
            var sat = new THREE.Mesh(
                new THREE.OctahedronGeometry(0.022, 0),
                new THREE.MeshBasicMaterial({ color: color, transparent: true, opacity: 0.75 })
            );
            var angle = (s / satCount) * Math.PI * 2;
            sat.userData.orbit = {
                angle: angle,
                radius: coreSize * 3.8 + (s % 4) * 0.05,
                speed: (0.35 + s * 0.07) * (0.7 + activity)
            };
            satellites.add(sat);
        }
        cluster.add(satellites);

        var dendriteOrigin = new THREE.Vector3(0, 0, 0);
        var dendriteDir = position.clone().normalize();
        createDendriteBranch(dendriteOrigin, dendriteDir, isMobile ? 2 : 3, domain.color, cluster);

        cluster.userData.parts = { core: core, glow: glow, ring: ring, satellites: satellites };
        return cluster;
    }

    function isPathActive(path, domain) {
        if (domain === 'all') return true;
        if (path.domainId) return path.domainId === domain;
        if (path.domainIds && path.domainIds.length) {
            return path.domainIds.indexOf(domain) >= 0;
        }
        return false;
    }

    function createSynapseLines(clusters) {
        var lines = [];
        var i, j;
        for (i = 0; i < clusters.length; i++) {
            for (j = i + 1; j < clusters.length; j++) {
                var mid = clusters[i].position.clone().add(clusters[j].position).multiplyScalar(0.5);
                var perp = new THREE.Vector3().crossVectors(clusters[i].position, clusters[j].position).normalize();
                if (perp.lengthSq() < 0.01) perp.set(0, 1, 0);
                mid.add(perp.multiplyScalar(0.25));
                var curve = new THREE.QuadraticBezierCurve3(
                    clusters[i].position.clone(),
                    mid,
                    clusters[j].position.clone()
                );
                var pts = curve.getPoints(32);
                var geo = new THREE.BufferGeometry().setFromPoints(pts);
                var mat = new THREE.LineBasicMaterial({
                    color: cssVar('--accent', '#4F7FFF'),
                    transparent: true,
                    opacity: 0.14,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false
                });
                var line = new THREE.Line(geo, mat);
                line.userData.curve = curve;
                line.userData.domainIds = [
                    clusters[i].userData.domainId,
                    clusters[j].userData.domainId
                ];
                lines.push(line);
            }
        }
        return lines;
    }

    function createSignalPool(paths, count) {
        var signals = [];
        var i;
        for (i = 0; i < count; i++) {
            var path = paths[i % paths.length];
            if (!path || !path.curve) continue;
            var mesh = new THREE.Mesh(
                new THREE.SphereGeometry(0.022, 8, 8),
                new THREE.MeshBasicMaterial({
                    color: path.color || cssVar('--accent', '#4F7FFF'),
                    transparent: true,
                    opacity: 0.9,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false
                })
            );
            var trail = new THREE.Mesh(
                new THREE.SphereGeometry(0.038, 6, 6),
                new THREE.MeshBasicMaterial({
                    color: path.color || cssVar('--accent', '#4F7FFF'),
                    transparent: true,
                    opacity: 0.15,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false
                })
            );
            mesh.add(trail);
            signals.push({
                mesh: mesh,
                path: path,
                t: Math.random(),
                speed: 0.12 + Math.random() * 0.22
            });
        }
        return signals;
    }

    function createRipplePool(max) {
        var ripples = [];
        var i;
        for (i = 0; i < max; i++) {
            var ring = new THREE.Mesh(
                new THREE.TorusGeometry(0.1, 0.004, 8, 64),
                new THREE.MeshBasicMaterial({
                    color: cssVar('--accent', '#4F7FFF'),
                    transparent: true,
                    opacity: 0,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false
                })
            );
            ring.rotation.x = Math.PI / 2;
            ring.userData.life = -1;
            ripples.push(ring);
        }
        return ripples;
    }

    function spawnRipple(ripples, color, t) {
        var i;
        for (i = 0; i < ripples.length; i++) {
            if (ripples[i].userData.life < 0) {
                ripples[i].userData.life = 0;
                ripples[i].userData.birth = t;
                ripples[i].material.color.set(color);
                ripples[i].scale.setScalar(0.1);
                ripples[i].material.opacity = 0.45;
                return;
            }
        }
    }

    function initEegCanvas() {
        var canvas = document.getElementById('cortex3dEeg');
        if (!canvas) return;
        threeCtx.eegCanvas = canvas;
        threeCtx.eegCtx = canvas.getContext('2d');
        resizeEeg();
        for (var i = 0; i < 180; i++) threeCtx.eegBuffer.push(0);
    }

    function resizeEeg() {
        if (!threeCtx.eegCanvas || !threeCtx.host) return;
        var w = threeCtx.host.clientWidth;
        threeCtx.eegCanvas.width = w;
        threeCtx.eegCanvas.height = 56;
        if (threeCtx.eegBuffer.length > w) {
            threeCtx.eegBuffer = threeCtx.eegBuffer.slice(-w);
        }
        while (threeCtx.eegBuffer.length < w) {
            threeCtx.eegBuffer.unshift(0);
        }
    }

    function drawEeg(t, activity) {
        var ctx = threeCtx.eegCtx;
        var canvas = threeCtx.eegCanvas;
        if (!ctx || !canvas) return;

        var sample = Math.sin(t * 3.2) * 0.3
            + Math.sin(t * 7.1 + 1.2) * 0.15
            + Math.sin(t * 13.5) * 0.08
            + (Math.random() - 0.5) * 0.12 * activity;
        threeCtx.eegBuffer.push(sample);
        if (threeCtx.eegBuffer.length > canvas.width) threeCtx.eegBuffer.shift();

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        var accent = cssVar('--accent', '#4F7FFF');
        var grad = ctx.createLinearGradient(0, 0, canvas.width, 0);
        grad.addColorStop(0, hexToRgba(accent, 0));
        grad.addColorStop(0.2, hexToRgba(accent, 0.85));
        grad.addColorStop(0.8, hexToRgba(accent, 0.85));
        grad.addColorStop(1, hexToRgba(accent, 0));

        ctx.beginPath();
        var mid = canvas.height * 0.5;
        var i;
        for (i = 0; i < threeCtx.eegBuffer.length; i++) {
            var x = (i / threeCtx.eegBuffer.length) * canvas.width;
            var y = mid + threeCtx.eegBuffer[i] * 18;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        ctx.strokeStyle = grad;
        ctx.lineWidth = 1.5;
        ctx.stroke();

        ctx.globalAlpha = 0.25;
        ctx.beginPath();
        for (i = 0; i < threeCtx.eegBuffer.length; i++) {
            var x2 = (i / threeCtx.eegBuffer.length) * canvas.width;
            var y2 = mid + threeCtx.eegBuffer[i] * 10;
            if (i === 0) ctx.moveTo(x2, y2);
            else ctx.lineTo(x2, y2);
        }
        ctx.strokeStyle = hexToRgba(accent, 0.5);
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.globalAlpha = 1;
    }

    function updateStatusBar() {
        var el = document.getElementById('cortex3dStatus');
        if (!el) return;
        var text = el.querySelector('.cortex-3d-status-text');
        el.classList.remove('is-healthy', 'is-attention', 'is-critical');
        if (threeCtx.pulseStatus === 'healthy') {
            el.classList.add('is-healthy');
            if (text) text.textContent = 'Malha estável · ' + threeCtx.pulseScore + '%';
        } else if (threeCtx.pulseStatus === 'attention') {
            el.classList.add('is-attention');
            if (text) text.textContent = 'Sinais em atenção · ' + threeCtx.pulseScore + '%';
        } else if (threeCtx.pulseStatus === 'critical') {
            el.classList.add('is-critical');
            if (text) text.textContent = 'Alerta neural · ' + threeCtx.pulseScore + '%';
        } else if (text) {
            text.textContent = 'Malha ativa · ' + threeCtx.pulseScore + '%';
        }
    }

    function initThree() {
        var host = document.getElementById('cortex3d');
        if (!host || typeof THREE === 'undefined') return;

        threeCtx.host = host;
        threeCtx.tipEl = document.getElementById('cortex3dTip');
        threeCtx.raycaster = new THREE.Raycaster();
        threeCtx.clock = new THREE.Clock();
        threeCtx.pulseScore = parseInt(host.getAttribute('data-pulse-score') || '0', 10);
        threeCtx.pulseStatus = host.getAttribute('data-pulse-status') || 'idle';

        initEegCanvas();
        updateStatusBar();

        var width = host.clientWidth || 600;
        var height = host.clientHeight || 500;

        var scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x0a1018, 0.12);

        var camera = new THREE.PerspectiveCamera(36, width / height, 0.1, 100);
        camera.position.set(0, 0.15, 5.4);

        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(global.devicePixelRatio || 1, 2));
        renderer.setClearColor(0x000000, 0);
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.15;
        host.innerHTML = '';
        host.appendChild(renderer.domElement);

        var root = new THREE.Group();
        scene.add(root);

        var inner = new THREE.Group();
        root.add(inner);

        var accent = cssVar('--accent', '#4F7FFF');
        var particleCount = isMobile ? 900 : (prefersReduced ? 600 : 2200);
        var particles = createShaderParticleField(particleCount, 2.55);
        inner.add(particles);
        inner.add(createPlexusLines(isMobile ? 32 : 56, 2.15));
        createNeuralShells(inner);

        var leftBrain = createBrainHemisphere(-1, 1.38, accent);
        var rightBrain = createBrainHemisphere(1, 1.38, accent);
        inner.add(leftBrain.group);
        inner.add(rightBrain.group);
        threeCtx.brains = [leftBrain, rightBrain];

        var nucleus = createCoreNucleus(threeCtx.pulseScore, threeCtx.pulseStatus);
        inner.add(nucleus.group);

        var domains = payload.domains || [];
        var clusters = [];
        var tubePaths = [];

        domains.forEach(function (domain, idx) {
            var pos = fibonacciSphere(idx, Math.max(domains.length, 1), 2.12);
            var cluster = createDomainCluster(domain, pos);
            inner.add(cluster);
            clusters.push(cluster);

            var tubePath = createNeuralTube(new THREE.Vector3(0, 0, 0), pos, domain.color || accent, domain.id);
            inner.add(tubePath.tube);
            inner.add(tubePath.wire);
            tubePaths.push(tubePath);
        });

        var synapses = createSynapseLines(clusters);
        synapses.forEach(function (line) { inner.add(line); });

        var allPaths = tubePaths.concat(synapses.map(function (s) {
            return {
                curve: s.userData.curve,
                color: cssVar('--accent', '#4F7FFF'),
                domainId: null,
                domainIds: s.userData.domainIds || []
            };
        }));
        var signals = createSignalPool(allPaths, isMobile ? 18 : 36);
        signals.forEach(function (sig) { inner.add(sig.mesh); });

        var ripples = createRipplePool(6);
        ripples.forEach(function (r) { nucleus.group.add(r); });

        scene.add(new THREE.AmbientLight(0xffffff, 0.38));
        scene.add(new THREE.HemisphereLight(0x8eb4ff, 0x1a1028, 0.55));
        var key = new THREE.PointLight(accent, 2.2, 35);
        key.position.set(3.5, 2.5, 4.5);
        scene.add(key);
        var fill = new THREE.PointLight(0xa78bfa, 0.85, 22);
        fill.position.set(-3.5, -1.5, 2.5);
        scene.add(fill);
        var rim = new THREE.PointLight(0x38bdf8, 0.5, 18);
        rim.position.set(0, -3, -2);
        scene.add(rim);

        buildHudLabels(clusters);

        threeCtx.scene = scene;
        threeCtx.camera = camera;
        threeCtx.renderer = renderer;
        threeCtx.group = inner;
        threeCtx.clusters = clusters;
        threeCtx.synapses = synapses;
        threeCtx.tubes = tubePaths;
        threeCtx.signals = signals;
        threeCtx.ripples = ripples;
        threeCtx.core = nucleus;
        threeCtx.particles = particles;

        bindThreeEvents(host, renderer, camera, clusters);

        var lastRipple = 0;
        var beatInterval = 2.8 - (threeCtx.pulseScore / 100) * 1.2;

        function animate() {
            threeCtx.frame = requestAnimationFrame(animate);
            var t = threeCtx.clock.getElapsedTime();
            var dt = prefersReduced ? 0 : 0.016;
            var globalActivity = 0.4 + (threeCtx.pulseScore / 100) * 0.6;

            if (!prefersReduced) {
                inner.rotation.y += 0.0008 + globalActivity * 0.0004;
                inner.rotation.x = Math.sin(t * 0.12) * 0.05;

                threeCtx.brains.forEach(function (brain) {
                    deformBrainGeometry(brain.wireGeo, brain.radius, t + brain.side, 0.85);
                    if (brain.solid && brain.solid.geometry) {
                        deformBrainGeometry(brain.solid.geometry, brain.radius, t + brain.side, 0.85);
                    }
                    brain.group.rotation.y = Math.sin(t * 0.08 + brain.side) * 0.04;
                });

                if (particles.material.uniforms) {
                    particles.material.uniforms.uTime.value = t;
                    particles.material.uniforms.uOpacity.value = 0.55 + globalActivity * 0.35;
                }
                particles.rotation.y -= 0.0003;

                inner.children.forEach(function (child) {
                    if (child.userData.shellSpeed) {
                        child.rotation.y += 0.0008 * child.userData.shellSpeed;
                        child.rotation.z += 0.0004 * child.userData.shellSpeed;
                    }
                });

                var beat = 1 + Math.sin(t * (1.8 + globalActivity)) * 0.1;
                if (nucleus.glow) nucleus.glow.scale.setScalar(beat * 1.05);
                if (nucleus.glow2) nucleus.glow2.scale.setScalar(beat * 1.15);
                if (nucleus.core) nucleus.core.material.emissiveIntensity = 0.9 + Math.sin(t * 3) * 0.25;

                nucleus.group.children.forEach(function (child) {
                    if (child.userData.isOrbitRing) {
                        child.rotation.z = t * child.userData.orbitSpeed;
                    }
                });

                if (t - lastRipple > beatInterval) {
                    spawnRipple(ripples, nucleus.color, t);
                    lastRipple = t;
                }
                ripples.forEach(function (ring) {
                    if (ring.userData.life < 0) return;
                    var age = t - ring.userData.birth;
                    ring.scale.setScalar(0.1 + age * 1.8);
                    ring.material.opacity = Math.max(0, 0.45 - age * 0.35);
                    if (age > 1.3) ring.userData.life = -1;
                });

                clusters.forEach(function (cluster, idx) {
                    var parts = cluster.userData.parts;
                    var active = activeDomain === 'all' || cluster.userData.domainId === activeDomain;
                    var act = cluster.userData.activity || 0.5;
                    var targetScale = active ? 1.22 : 0.68;
                    var s = cluster.scale.x + (targetScale - cluster.scale.x) * 0.05;
                    cluster.scale.setScalar(s);

                    if (parts.core) {
                        parts.core.rotation.x = t * (0.4 + act);
                        parts.core.rotation.y = t * (0.55 + act * 0.5);
                        parts.core.material.emissiveIntensity = active ? 0.65 + act * 0.5 : 0.12;
                    }
                    if (parts.glow) {
                        parts.glow.material.opacity = active ? 0.22 + act * 0.2 : 0.04;
                        parts.glow.scale.setScalar(1 + Math.sin(t * 2 + idx) * 0.08);
                    }
                    if (parts.ring) {
                        parts.ring.rotation.z = t * (0.3 + idx * 0.04 + act * 0.2);
                    }
                    if (parts.satellites) {
                        parts.satellites.children.forEach(function (sat) {
                            var o = sat.userData.orbit;
                            if (!o) return;
                            o.angle += o.speed * dt * (active ? 1.2 : 0.25);
                            sat.position.set(
                                Math.cos(o.angle) * o.radius,
                                Math.sin(o.angle * 1.4) * o.radius * 0.38,
                                Math.sin(o.angle) * o.radius
                            );
                            sat.rotation.x = t * 2;
                        });
                    }
                });

                threeCtx.tubes.forEach(function (tp, idx) {
                    var active = activeDomain === 'all' || tp.domainId === activeDomain;
                    tp.tube.material.opacity = active ? 0.38 + Math.sin(t * 2 + idx) * 0.12 : 0.06;
                    tp.wire.material.opacity = active ? 0.22 : 0.04;
                });

                synapses.forEach(function (line, idx) {
                    var pathActive = isPathActive(
                        { domainId: null, domainIds: line.userData.domainIds || [] },
                        activeDomain
                    );
                    var pulse = Math.abs(Math.sin(t * 1.8 + idx * 0.7)) * 0.16;
                    line.material.opacity = pathActive ? 0.08 + pulse : 0.025;
                });

                threeCtx.signals.forEach(function (sig) {
                    var active = isPathActive(sig.path, activeDomain);
                    sig.t += sig.speed * dt * (active ? 1.4 : 0.2);
                    if (sig.t > 1) sig.t -= 1;
                    if (sig.path.curve) {
                        var pt = sig.path.curve.getPoint(sig.t);
                        sig.mesh.position.copy(pt);
                    }
                    sig.mesh.material.opacity = active ? 0.85 : 0.06;
                    sig.mesh.visible = active;
                });

                camera.position.x += (threeCtx.mouse.tx * 0.5 - camera.position.x) * 0.035;
                camera.position.y += (threeCtx.mouse.ty * 0.28 + 0.12 - camera.position.y) * 0.035;
                camera.lookAt(0, 0, 0);

                drawEeg(t, globalActivity);
            }

            updateHudLabels(camera, clusters);
            renderer.render(scene, camera);
        }
        animate();
    }

    function buildHudLabels(clusters) {
        var hud = document.getElementById('cortex3dHud');
        if (!hud) return;
        hud.innerHTML = '';
        threeCtx.hudLabels = clusters.map(function (cluster) {
            var el = document.createElement('span');
            el.className = 'cortex-3d-hud-label';
            el.textContent = cluster.userData.label || '';
            el.style.setProperty('--label-color', domainColor(cluster.userData.domainId));
            hud.appendChild(el);
            return { el: el, cluster: cluster };
        });
    }

    function updateHudLabels(camera, clusters) {
        if (!threeCtx.hudLabels.length || !threeCtx.host) return;
        var w = threeCtx.host.clientWidth;
        var h = threeCtx.host.clientHeight;
        var vec = new THREE.Vector3();

        threeCtx.hudLabels.forEach(function (item) {
            var active = activeDomain === 'all' || item.cluster.userData.domainId === activeDomain;
            item.el.classList.toggle('is-visible', active);
            if (!active) return;

            vec.copy(item.cluster.position);
            item.cluster.localToWorld(vec);
            vec.project(camera);
            var x = (vec.x * 0.5 + 0.5) * w;
            var y = (-vec.y * 0.5 + 0.5) * h;
            if (vec.z > 1) {
                item.el.style.opacity = '0';
                return;
            }
            item.el.style.left = x + 'px';
            item.el.style.top = (y - 12) + 'px';
            item.el.style.opacity = String(0.7 + (item.cluster.userData.activity || 0.5) * 0.3);
        });
    }

    function bindThreeEvents(host, renderer, camera, clusters) {
        var pickables = [];
        clusters.forEach(function (c) {
            pickables.push(c.userData.parts.core);
        });

        host.addEventListener('mousemove', function (ev) {
            var rect = renderer.domElement.getBoundingClientRect();
            threeCtx.mouse.x = ((ev.clientX - rect.left) / rect.width) * 2 - 1;
            threeCtx.mouse.y = -((ev.clientY - rect.top) / rect.height) * 2 + 1;
            threeCtx.mouse.tx = threeCtx.mouse.x;
            threeCtx.mouse.ty = threeCtx.mouse.y;

            threeCtx.raycaster.setFromCamera(threeCtx.mouse, camera);
            var hits = threeCtx.raycaster.intersectObjects(pickables);
            var tip = threeCtx.tipEl;
            if (hits.length && tip) {
                var cluster = hits[0].object.parent;
                var d = domainById(cluster.userData.domainId);
                tip.hidden = false;
                tip.style.left = (ev.clientX - rect.left) + 'px';
                tip.style.top = (ev.clientY - rect.top) + 'px';
                tip.innerHTML = '<strong>' + (d ? d.label : cluster.userData.label) + '</strong>' +
                    '<small>Score ' + (d ? d.score : '—') + ' · ' + (d ? d.entities : 0) + ' nós · atividade ' +
                    Math.round((cluster.userData.activity || 0) * 100) + '%</small>';
                host.style.cursor = 'pointer';
            } else if (tip) {
                tip.hidden = true;
                host.style.cursor = 'grab';
            }
        });

        host.addEventListener('click', function () {
            threeCtx.raycaster.setFromCamera(threeCtx.mouse, camera);
            var hits = threeCtx.raycaster.intersectObjects(pickables);
            if (hits[0]) {
                setActiveDomain(hits[0].object.parent.userData.domainId);
            }
        });

        host.addEventListener('mouseleave', function () {
            if (threeCtx.tipEl) threeCtx.tipEl.hidden = true;
            threeCtx.mouse.tx = 0;
            threeCtx.mouse.ty = 0;
        });
    }

    function setActiveDomain(domain) {
        activeDomain = domain;
        document.querySelectorAll('[data-cortex-domain]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-cortex-domain') === domain);
        });
        initGraph(domain);
    }

    function bindDomainFilters() {
        document.querySelectorAll('[data-cortex-domain]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setActiveDomain(btn.getAttribute('data-cortex-domain'));
            });
        });
    }

    function onResize() {
        if (graphChart) graphChart.resize();
        if (threeCtx.renderer && threeCtx.camera && threeCtx.host) {
            var w = threeCtx.host.clientWidth;
            var h = threeCtx.host.clientHeight;
            threeCtx.camera.aspect = w / h;
            threeCtx.camera.updateProjectionMatrix();
            threeCtx.renderer.setSize(w, h);
            resizeEeg();
        }
    }

    initGraph('all');
    initThree();
    bindDomainFilters();
    global.addEventListener('resize', onResize);
})(window);
