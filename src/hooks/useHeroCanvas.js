import { useEffect } from "react";
import * as THREE from "three";

/**
 * Interactive energy-grid canvas for the hero section.
 * Mounts a Three.js point/line field into the element referenced by mountRef.
 */
export function useHeroCanvas(mountRef) {
  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;

    const reduceMotion =
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    let W = mount.clientWidth,
      H = mount.clientHeight;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(52, W / H, 0.1, 100);
    camera.position.set(0, 0, 14);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.8));
    renderer.setSize(W, H);
    mount.appendChild(renderer.domElement);

    const isMobile = W < 720;
    const COUNT = reduceMotion ? 0 : isMobile ? 46 : 100;
    const BOUND_X = 11,
      BOUND_Y = 6,
      BOUND_Z = 4;

    const rest = [];
    const posArr = [];
    for (let i = 0; i < COUNT; i++) {
      const p = {
        x: (Math.random() * 2 - 1) * BOUND_X,
        y: (Math.random() * 2 - 1) * BOUND_Y,
        z: (Math.random() * 2 - 1) * BOUND_Z,
        phase: Math.random() * Math.PI * 2,
      };
      rest.push(p);
      posArr.push(p.x, p.y, p.z);
    }
    const positions = new Float32Array(posArr);
    const current = positions.slice();

    function makeDot(color) {
      const c = document.createElement("canvas");
      c.width = c.height = 64;
      const ctx = c.getContext("2d");
      const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
      g.addColorStop(0, color);
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, 64, 64);
      return new THREE.CanvasTexture(c);
    }

    const pointsGeo = new THREE.BufferGeometry();
    pointsGeo.setAttribute("position", new THREE.BufferAttribute(current, 3));
    const pointsMat = new THREE.PointsMaterial({
      size: isMobile ? 0.22 : 0.26,
      map: makeDot("rgba(255,255,255,1)"),
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      sizeAttenuation: true,
    });
    const pointCloud = new THREE.Points(pointsGeo, pointsMat);
    scene.add(pointCloud);

    const edgeIdx = [];
    const maxDist = isMobile ? 3.4 : 3.1;
    for (let a = 0; a < COUNT; a++) {
      for (let b = a + 1; b < COUNT; b++) {
        const dx = rest[a].x - rest[b].x,
          dy = rest[a].y - rest[b].y,
          dz = rest[a].z - rest[b].z;
        const d = Math.sqrt(dx * dx + dy * dy + dz * dz);
        if (d < maxDist) edgeIdx.push([a, b, d]);
      }
    }
    const linePositions = new Float32Array(edgeIdx.length * 6);
    const lineGeo = new THREE.BufferGeometry();
    lineGeo.setAttribute("position", new THREE.BufferAttribute(linePositions, 3));
    const lineMat = new THREE.LineBasicMaterial({
      color: 0xdff2fc,
      transparent: true,
      opacity: 0.28,
    });
    const lineSegs = new THREE.LineSegments(lineGeo, lineMat);
    scene.add(lineSegs);

    const pulseEdges = edgeIdx
      .slice()
      .sort((x, y) => x[2] - y[2])
      .slice(0, isMobile ? 8 : 16);
    const pulseGeo = new THREE.BufferGeometry();
    const pulsePos = new Float32Array(pulseEdges.length * 3);
    pulseGeo.setAttribute("position", new THREE.BufferAttribute(pulsePos, 3));
    const pulseMat = new THREE.PointsMaterial({
      size: isMobile ? 0.34 : 0.4,
      map: makeDot("rgba(120,207,255,1)"),
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      sizeAttenuation: true,
    });
    const pulsePoints = new THREE.Points(pulseGeo, pulseMat);
    scene.add(pulsePoints);
    const pulseT = pulseEdges.map(() => Math.random());

    const mouse = new THREE.Vector2(0, 0);
    const mouseWorld = new THREE.Vector3(9999, 9999, 0);
    const raycaster = new THREE.Raycaster();
    const plane = new THREE.Plane(new THREE.Vector3(0, 0, 1), 0);
    let targetCamX = 0,
      targetCamY = 0;

    function onMove(clientX, clientY) {
      const r = mount.getBoundingClientRect();
      mouse.x = ((clientX - r.left) / r.width) * 2 - 1;
      mouse.y = -((clientY - r.top) / r.height) * 2 + 1;
      raycaster.setFromCamera(mouse, camera);
      const hit = new THREE.Vector3();
      raycaster.ray.intersectPlane(plane, hit);
      if (hit) mouseWorld.copy(hit);
      targetCamX = mouse.x * 0.6;
      targetCamY = mouse.y * 0.35;
    }
    const onMouseMove = (e) => onMove(e.clientX, e.clientY);
    const onTouchMove = (e) => {
      if (e.touches && e.touches[0]) onMove(e.touches[0].clientX, e.touches[0].clientY);
    };
    window.addEventListener("mousemove", onMouseMove);
    window.addEventListener("touchmove", onTouchMove, { passive: true });

    const onResize = () => {
      W = mount.clientWidth;
      H = mount.clientHeight;
      camera.aspect = W / H;
      camera.updateProjectionMatrix();
      renderer.setSize(W, H);
    };
    window.addEventListener("resize", onResize);

    const REPEL_RADIUS = 3.2,
      REPEL_STRENGTH = 1.6;
    const clock = new THREE.Clock();
    let rafId;

    function animate() {
      rafId = requestAnimationFrame(animate);
      const t = clock.getElapsedTime();

      camera.position.x += (targetCamX - camera.position.x) * 0.03;
      camera.position.y += (targetCamY - camera.position.y) * 0.03;
      camera.lookAt(0, 0, 0);

      const arr = pointsGeo.attributes.position.array;
      for (let i = 0; i < COUNT; i++) {
        const ix = i * 3;
        let rx = rest[i].x + Math.sin(t * 0.3 + rest[i].phase) * 0.35;
        let ry = rest[i].y + Math.cos(t * 0.25 + rest[i].phase) * 0.35;
        const rz = rest[i].z;

        const dx = rx - mouseWorld.x,
          dy = ry - mouseWorld.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < REPEL_RADIUS) {
          const force = (1 - dist / REPEL_RADIUS) * REPEL_STRENGTH;
          const nx = dx / (dist || 1),
            ny = dy / (dist || 1);
          rx += nx * force;
          ry += ny * force;
        }
        arr[ix] = rx;
        arr[ix + 1] = ry;
        arr[ix + 2] = rz;
      }
      pointsGeo.attributes.position.needsUpdate = true;

      const larr = lineGeo.attributes.position.array;
      for (let e = 0; e < edgeIdx.length; e++) {
        const ai = edgeIdx[e][0] * 3,
          bi = edgeIdx[e][1] * 3;
        const li = e * 6;
        larr[li] = arr[ai];
        larr[li + 1] = arr[ai + 1];
        larr[li + 2] = arr[ai + 2];
        larr[li + 3] = arr[bi];
        larr[li + 4] = arr[bi + 1];
        larr[li + 5] = arr[bi + 2];
      }
      lineGeo.attributes.position.needsUpdate = true;

      const parr = pulseGeo.attributes.position.array;
      for (let p = 0; p < pulseEdges.length; p++) {
        pulseT[p] += 0.0035;
        if (pulseT[p] > 1) pulseT[p] = 0;
        const ai2 = pulseEdges[p][0] * 3,
          bi2 = pulseEdges[p][1] * 3;
        const pi = p * 3;
        parr[pi] = arr[ai2] + (arr[bi2] - arr[ai2]) * pulseT[p];
        parr[pi + 1] = arr[ai2 + 1] + (arr[bi2 + 1] - arr[ai2 + 1]) * pulseT[p];
        parr[pi + 2] = arr[ai2 + 2] + (arr[bi2 + 2] - arr[ai2 + 2]) * pulseT[p];
      }
      pulseGeo.attributes.position.needsUpdate = true;

      renderer.render(scene, camera);
    }
    animate();

    return () => {
      cancelAnimationFrame(rafId);
      window.removeEventListener("mousemove", onMouseMove);
      window.removeEventListener("touchmove", onTouchMove);
      window.removeEventListener("resize", onResize);
      pointsGeo.dispose();
      lineGeo.dispose();
      pulseGeo.dispose();
      pointsMat.dispose();
      lineMat.dispose();
      pulseMat.dispose();
      renderer.dispose();
      if (mount.contains(renderer.domElement)) {
        mount.removeChild(renderer.domElement);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
}
