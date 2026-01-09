const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

const dnaCountEl = document.getElementById("dnaCount");
const hpTextEl = document.getElementById("hpText");
const hpBarEl = document.getElementById("hpBar");
const dashBarEl = document.getElementById("dashBar");
const dashLabelEl = document.getElementById("dashLabel");
const evolutionBarEl = document.getElementById("evolutionBar");
const evolutionLabelEl = document.getElementById("evolutionLabel");
const floatingLayer = document.getElementById("floatingTextLayer");

const startScreen = document.getElementById("startScreen");
const finalScreen = document.getElementById("finalScreen");
const startButton = document.getElementById("startButton");
const restartButton = document.getElementById("restartButton");
const toggleMenuButton = document.getElementById("toggleMenu");
const upgradePanel = document.getElementById("upgradePanel");
const closeMenuButton = document.getElementById("closeMenu");

const tutorialEl = document.getElementById("tutorial");

const dnaTypes = [
  { type: "comum", value: 10, color: "#3fffd4", chance: 0.75 },
  { type: "raro", value: 25, color: "#3fb6ff", chance: 0.2 },
  { type: "epico", value: 60, color: "#8a6bff", chance: 0.05 },
];

const upgrades = {
  vida: { level: 0, baseCost: 50 },
  tamanho: { level: 0, baseCost: 60 },
  espinhos: { level: 0, baseCost: 70 },
  dash: { level: 0, baseCost: 55 },
};

const evolutionStages = [0, 150, 320, 520, 760, 1050];
const evolutionColors = [
  "#3fffd4",
  "#5fffb1",
  "#7ff7ff",
  "#b890ff",
  "#ffc46b",
  "#ffd6ff",
];

const state = {
  running: false,
  paused: false,
  lastTime: 0,
  introTime: 0,
  meteorY: -200,
  meteorX: canvas.width * 0.65,
  meteorSpeed: 180,
  tutorialTimer: 8000,
  evolutionLevel: 1,
  dnaSpent: 0,
  bossSpawned: false,
};

const player = {
  x: canvas.width / 2,
  y: canvas.height / 2,
  radius: 24,
  maxRadius: 40,
  speed: 2.4,
  hp: 120,
  maxHp: 120,
  dna: 0,
  spikes: 0,
  color: evolutionColors[0],
  dash: {
    ready: true,
    cooldown: 1400,
    duration: 24,
    timer: 0,
    speedMultiplier: 2.4,
    lastUse: 0,
    trail: [],
  },
};

const world = {
  dnaOrbs: [],
  enemies: [],
  particles: [],
  floatingTexts: [],
  waves: [],
};

const keys = {
  ArrowUp: false,
  ArrowDown: false,
  ArrowLeft: false,
  ArrowRight: false,
  w: false,
  a: false,
  s: false,
  d: false,
};

function initWaterParticles() {
  world.particles = Array.from({ length: 120 }).map(() => ({
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    radius: 1 + Math.random() * 2.4,
    opacity: 0.2 + Math.random() * 0.5,
    speed: 0.3 + Math.random() * 0.6,
  }));
}

function spawnDnaOrb({ x, y, type }) {
  const dnaType = type || rollDnaType();
  world.dnaOrbs.push({
    x,
    y,
    radius: 6 + Math.random() * 4,
    type: dnaType.type,
    value: dnaType.value,
    color: dnaType.color,
    vy: -0.2 - Math.random() * 0.6,
    vx: (Math.random() - 0.5) * 0.8,
  });
}

function spawnEnemy({ isBoss = false } = {}) {
  const radiusBase = 14 + Math.random() * 28 + state.evolutionLevel * 3;
  const radius = isBoss ? 80 : radiusBase;
  const shapes = ["triangle", "hex", "star"];
  const motions = ["wave", "spiral", "wander"];
  const colors = ["#ff6f7d", "#ffb347", "#59d4ff"];
  const side = Math.random() < 0.5 ? -50 : canvas.width + 50;
  const y = 60 + Math.random() * (canvas.height - 120);

  world.enemies.push({
    x: side,
    y,
    radius,
    shape: shapes[Math.floor(Math.random() * shapes.length)],
    motion: motions[Math.floor(Math.random() * motions.length)],
    color: colors[Math.floor(Math.random() * colors.length)],
    speed: Math.max(0.6, 2.8 - radius * 0.03),
    angle: Math.random() * Math.PI * 2,
    rotation: (Math.random() - 0.5) * 0.03,
    isBoss,
  });
}

function rollDnaType() {
  const roll = Math.random();
  let sum = 0;
  for (const dnaType of dnaTypes) {
    sum += dnaType.chance;
    if (roll <= sum) return dnaType;
  }
  return dnaTypes[0];
}

function updatePlayer(delta) {
  let dx = 0;
  let dy = 0;
  if (keys.ArrowUp || keys.w) dy -= 1;
  if (keys.ArrowDown || keys.s) dy += 1;
  if (keys.ArrowLeft || keys.a) dx -= 1;
  if (keys.ArrowRight || keys.d) dx += 1;

  const length = Math.hypot(dx, dy) || 1;
  const moveSpeed = player.speed * (player.dash.timer > 0 ? player.dash.speedMultiplier : 1);
  player.x += (dx / length) * moveSpeed * delta;
  player.y += (dy / length) * moveSpeed * delta;

  player.x = Math.max(player.radius, Math.min(canvas.width - player.radius, player.x));
  player.y = Math.max(player.radius, Math.min(canvas.height - player.radius, player.y));

  if (player.dash.timer > 0) {
    player.dash.timer -= 1;
    player.dash.trail.push({ x: player.x, y: player.y, radius: player.radius });
    if (player.dash.trail.length > 14) player.dash.trail.shift();
  } else {
    player.dash.trail = [];
  }

  updateDashHud();
}

function updateDnaOrbs(delta) {
  world.dnaOrbs.forEach((orb) => {
    orb.x += orb.vx * delta * 20;
    orb.y += orb.vy * delta * 20;
  });

  world.dnaOrbs = world.dnaOrbs.filter((orb) => {
    const distance = Math.hypot(player.x - orb.x, player.y - orb.y);
    if (distance < player.radius + orb.radius) {
      player.dna += orb.value;
      addFloatingText(`+${orb.value}`, orb.x, orb.y, orb.color);
      updateHud();
      return false;
    }
    return orb.y > -20 && orb.y < canvas.height + 20;
  });
}

function updateEnemies(delta) {
  world.enemies.forEach((enemy) => {
    enemy.angle += enemy.rotation;
    if (enemy.motion === "wave") {
      enemy.x += enemy.speed * delta * 30;
      enemy.y += Math.sin(enemy.angle) * 0.6;
    } else if (enemy.motion === "spiral") {
      enemy.x += enemy.speed * delta * 24;
      enemy.y += Math.cos(enemy.angle) * 0.9;
    } else {
      enemy.x += enemy.speed * delta * 26;
      enemy.y += Math.sin(enemy.angle * 0.7) * 0.4;
    }
  });

  world.enemies = world.enemies.filter((enemy) => {
    const distance = Math.hypot(player.x - enemy.x, player.y - enemy.y);
    if (distance < player.radius + enemy.radius) {
      resolveCombat(enemy);
      return false;
    }
    return enemy.x > -150 && enemy.x < canvas.width + 150;
  });
}

function resolveCombat(enemy) {
  const playerPower = player.radius + player.spikes * 4;
  const enemyPower = enemy.radius * 1.1;
  if (playerPower >= enemyPower) {
    const reward = Math.round(enemy.radius * 1.6 + 20);
    player.dna += reward;
    addFloatingText(`+${reward}`, enemy.x, enemy.y, "#ffd36b");
    spawnDnaBurst(enemy.x, enemy.y, enemy.isBoss ? 8 : 4);
  } else {
    const damage = Math.max(6, Math.round(enemy.radius * 0.25));
    player.hp = Math.max(0, player.hp - damage);
    addFloatingText(`-${damage} HP`, player.x, player.y, "#ff6f7d");
  }
  updateHud();
}

function spawnDnaBurst(x, y, amount) {
  for (let i = 0; i < amount; i += 1) {
    spawnDnaOrb({
      x: x + (Math.random() - 0.5) * 30,
      y: y + (Math.random() - 0.5) * 30,
    });
  }
}

function addFloatingText(text, x, y, color) {
  const node = document.createElement("span");
  node.className = "floating-text";
  node.textContent = text;
  node.style.left = `${x}px`;
  node.style.top = `${y}px`;
  node.style.color = color;
  floatingLayer.appendChild(node);
  setTimeout(() => node.remove(), 1200);
}

function updateHud() {
  dnaCountEl.textContent = player.dna;
  hpTextEl.textContent = `${Math.round(player.hp)}/${player.maxHp}`;
  hpBarEl.style.width = `${(player.hp / player.maxHp) * 100}%`;
}

function updateDashHud() {
  const now = performance.now();
  const elapsed = now - player.dash.lastUse;
  const percent = Math.min(1, elapsed / player.dash.cooldown);
  dashBarEl.style.width = `${percent * 100}%`;
  player.dash.ready = percent >= 1;
  dashLabelEl.textContent = player.dash.ready ? "Pronto" : "Recarregando";
}

function updateEvolutionHud() {
  const lastStage = evolutionStages[evolutionStages.length - 1];
  const nextStage = evolutionStages[state.evolutionLevel] ?? lastStage;
  const prevStage = evolutionStages[state.evolutionLevel - 1] ?? 0;
  const progress = Math.min(1, (state.dnaSpent - prevStage) / (nextStage - prevStage || 1));
  evolutionBarEl.style.width = `${progress * 100}%`;
  evolutionLabelEl.textContent = `Fase ${state.evolutionLevel}`;
}

function checkEvolution() {
  if (state.evolutionLevel >= evolutionStages.length) return;
  const target = evolutionStages[state.evolutionLevel];
  if (state.dnaSpent >= target) {
    state.evolutionLevel += 1;
    player.color = evolutionColors[Math.min(state.evolutionLevel - 1, evolutionColors.length - 1)];
    player.maxHp += 20;
    player.hp = Math.min(player.maxHp, player.hp + 20);
    player.maxRadius += 6;
    player.radius = Math.min(player.radius + 3, player.maxRadius);
    tutorialEl.textContent = `Nova evolução alcançada! Fase ${state.evolutionLevel}. Sua célula se adapta ao ambiente.`;
    tutorialEl.classList.add("pulse");
    setTimeout(() => tutorialEl.classList.remove("pulse"), 2000);
    updateHud();
    updateEvolutionHud();
    if (state.evolutionLevel > evolutionStages.length - 1) {
      showFinalScene();
    }
  }
}

function showFinalScene() {
  finalScreen.classList.add("overlay--active");
  state.running = false;
}

function updateUpgradePanel() {
  Object.keys(upgrades).forEach((key) => {
    const levelEl = upgradePanel.querySelector(`[data-level="${key}"]`);
    const costEl = upgradePanel.querySelector(`[data-cost="${key}"]`);
    const actionButton = upgradePanel.querySelector(`[data-action="${key}"]`);
    const cost = getUpgradeCost(key);

    levelEl.textContent = upgrades[key].level;
    costEl.textContent = cost;
    actionButton.disabled = player.dna < cost;
  });
}

function getUpgradeCost(key) {
  const upgrade = upgrades[key];
  return Math.round(upgrade.baseCost * (1 + upgrade.level * 0.6));
}

function applyUpgrade(key) {
  const cost = getUpgradeCost(key);
  if (player.dna < cost) return;

  player.dna -= cost;
  state.dnaSpent += cost;
  upgrades[key].level += 1;

  if (key === "vida") {
    player.maxHp += 20;
    player.hp = Math.min(player.maxHp, player.hp + 20);
  }

  if (key === "tamanho") {
    player.maxRadius += 6;
    player.radius = Math.min(player.radius + 4, player.maxRadius);
  }

  if (key === "espinhos") {
    player.spikes += 1;
  }

  if (key === "dash") {
    player.dash.cooldown = Math.max(600, player.dash.cooldown - 150);
    player.dash.duration = Math.min(42, player.dash.duration + 4);
    player.dash.speedMultiplier = Math.min(3.6, player.dash.speedMultiplier + 0.2);
  }

  updateHud();
  updateUpgradePanel();
  updateEvolutionHud();
  checkEvolution();
}

function drawWaterParticles() {
  ctx.save();
  ctx.globalCompositeOperation = "lighter";
  world.particles.forEach((particle) => {
    ctx.beginPath();
    ctx.fillStyle = `rgba(63, 255, 212, ${particle.opacity})`;
    ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
    ctx.fill();
    particle.y += particle.speed;
    if (particle.y > canvas.height + 10) {
      particle.y = -10;
      particle.x = Math.random() * canvas.width;
    }
  });
  ctx.restore();
}

function drawDnaOrbs() {
  world.dnaOrbs.forEach((orb) => {
    ctx.beginPath();
    ctx.fillStyle = orb.color;
    ctx.shadowColor = orb.color;
    ctx.shadowBlur = 12;
    ctx.arc(orb.x, orb.y, orb.radius, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowBlur = 0;
  });
}

function drawEnemy(enemy) {
  ctx.save();
  ctx.translate(enemy.x, enemy.y);
  ctx.rotate(enemy.angle);
  ctx.strokeStyle = enemy.color;
  ctx.fillStyle = `${enemy.color}22`;
  ctx.lineWidth = 2;

  const points = enemy.shape === "triangle" ? 3 : enemy.shape === "hex" ? 6 : 5;
  ctx.beginPath();
  for (let i = 0; i < points; i += 1) {
    const angle = (i / points) * Math.PI * 2;
    const radius = enemy.shape === "star" && i % 2 === 0 ? enemy.radius * 0.5 : enemy.radius;
    ctx.lineTo(Math.cos(angle) * radius, Math.sin(angle) * radius);
  }
  ctx.closePath();
  ctx.fill();
  ctx.stroke();
  ctx.restore();
}

function drawPlayer() {
  if (player.dash.trail.length) {
    player.dash.trail.forEach((trail, index) => {
      const alpha = (index + 1) / player.dash.trail.length;
      ctx.beginPath();
      ctx.fillStyle = `rgba(63, 255, 212, ${alpha * 0.2})`;
      ctx.arc(trail.x, trail.y, trail.radius * 0.9, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  ctx.beginPath();
  ctx.fillStyle = player.color;
  ctx.shadowColor = player.color;
  ctx.shadowBlur = 18;
  ctx.arc(player.x, player.y, player.radius, 0, Math.PI * 2);
  ctx.fill();
  ctx.shadowBlur = 0;

  if (player.spikes > 0) {
    const spikes = 6 + player.spikes * 2;
    const spikeLength = 6 + player.spikes * 2;
    ctx.strokeStyle = "rgba(255, 255, 255, 0.6)";
    for (let i = 0; i < spikes; i += 1) {
      const angle = (i / spikes) * Math.PI * 2;
      const sx = player.x + Math.cos(angle) * player.radius;
      const sy = player.y + Math.sin(angle) * player.radius;
      const ex = player.x + Math.cos(angle) * (player.radius + spikeLength);
      const ey = player.y + Math.sin(angle) * (player.radius + spikeLength);
      ctx.beginPath();
      ctx.moveTo(sx, sy);
      ctx.lineTo(ex, ey);
      ctx.stroke();
    }
  }
}

function drawIntroScene(delta) {
  state.introTime += delta * 1000;
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  drawStars();

  state.meteorY += state.meteorSpeed * delta * 0.7;
  ctx.save();
  ctx.fillStyle = "#ffb347";
  ctx.shadowColor = "#ffb347";
  ctx.shadowBlur = 20;
  ctx.beginPath();
  ctx.arc(state.meteorX, state.meteorY, 18, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();

  ctx.strokeStyle = "rgba(255, 179, 71, 0.6)";
  ctx.lineWidth = 4;
  ctx.beginPath();
  ctx.moveTo(state.meteorX - 120, state.meteorY - 80);
  ctx.lineTo(state.meteorX - 10, state.meteorY - 6);
  ctx.stroke();

  drawWaterSurface();

  if (state.meteorY > canvas.height * 0.68) {
    const splashRadius = 40 + Math.sin(state.introTime * 0.01) * 6;
    ctx.beginPath();
    ctx.strokeStyle = "rgba(63, 255, 212, 0.8)";
    ctx.lineWidth = 2;
    ctx.arc(state.meteorX, canvas.height * 0.7, splashRadius, 0, Math.PI * 2);
    ctx.stroke();
  }
}

function drawStars() {
  ctx.fillStyle = "#030c16";
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = "rgba(255,255,255,0.4)";
  for (let i = 0; i < 80; i += 1) {
    ctx.fillRect(Math.random() * canvas.width, Math.random() * canvas.height * 0.5, 1, 1);
  }
}

function drawWaterSurface() {
  ctx.save();
  ctx.fillStyle = "rgba(3, 18, 34, 0.8)";
  ctx.fillRect(0, canvas.height * 0.65, canvas.width, canvas.height * 0.35);
  ctx.strokeStyle = "rgba(63, 255, 212, 0.3)";
  ctx.beginPath();
  for (let x = 0; x < canvas.width; x += 40) {
    const y = canvas.height * 0.68 + Math.sin((x + state.introTime * 0.06) * 0.04) * 4;
    ctx.lineTo(x, y);
  }
  ctx.stroke();
  ctx.restore();
}

function maybeSpawnEnemy(delta) {
  if (state.paused) return;
  if (Math.random() < 0.015 * delta * 60) {
    spawnEnemy();
  }

  if (!state.bossSpawned && state.evolutionLevel >= 4 && Math.random() < 0.003) {
    state.bossSpawned = true;
    spawnEnemy({ isBoss: true });
  }
}

function maybeSpawnDna(delta) {
  if (state.paused) return;
  if (Math.random() < 0.02 * delta * 60) {
    spawnDnaOrb({
      x: 40 + Math.random() * (canvas.width - 80),
      y: 40 + Math.random() * (canvas.height - 80),
    });
  }
}

function update(delta) {
  if (!state.running || state.paused) return;
  updatePlayer(delta);
  updateDnaOrbs(delta);
  updateEnemies(delta);
  maybeSpawnEnemy(delta);
  maybeSpawnDna(delta);

  if (player.hp <= 0) {
    player.hp = player.maxHp;
    player.dna = Math.max(0, player.dna - 40);
    updateHud();
  }

  if (state.tutorialTimer > 0) {
    state.tutorialTimer -= delta * 1000;
    if (state.tutorialTimer <= 0) {
      tutorialEl.style.display = "none";
    }
  }
}

function render() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = "#041421";
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  drawWaterParticles();
  drawDnaOrbs();
  world.enemies.forEach(drawEnemy);
  drawPlayer();
}

function loop(timestamp) {
  const delta = (timestamp - state.lastTime) / 1000 || 0;
  state.lastTime = timestamp;

  if (!state.running) {
    drawIntroScene(delta);
    requestAnimationFrame(loop);
    return;
  }

  update(delta);
  render();

  if (!state.running) return;
  requestAnimationFrame(loop);
}

function startGame() {
  state.running = true;
  state.paused = false;
  startScreen.classList.remove("overlay--active");
  finalScreen.classList.remove("overlay--active");
  tutorialEl.style.display = "block";
  state.tutorialTimer = 8000;
  updateHud();
  updateEvolutionHud();
  updateUpgradePanel();
}

function resetGame() {
  player.x = canvas.width / 2;
  player.y = canvas.height / 2;
  player.radius = 24;
  player.maxRadius = 40;
  player.speed = 2.4;
  player.hp = 120;
  player.maxHp = 120;
  player.dna = 0;
  player.spikes = 0;
  player.color = evolutionColors[0];
  player.dash.cooldown = 1400;
  player.dash.duration = 24;
  player.dash.speedMultiplier = 2.4;
  player.dash.lastUse = 0;

  Object.keys(upgrades).forEach((key) => {
    upgrades[key].level = 0;
  });

  state.running = false;
  state.paused = false;
  state.dnaSpent = 0;
  state.evolutionLevel = 1;
  state.bossSpawned = false;
  world.dnaOrbs = [];
  world.enemies = [];
  updateHud();
  updateEvolutionHud();
  updateUpgradePanel();
  startScreen.classList.add("overlay--active");
}

function toggleMenu() {
  state.paused = !state.paused;
  upgradePanel.classList.toggle("menu--open", state.paused);
  updateUpgradePanel();
}

function handleDash() {
  if (!player.dash.ready || state.paused) return;
  player.dash.timer = player.dash.duration;
  player.dash.lastUse = performance.now();
  updateDashHud();
}

startButton.addEventListener("click", () => {
  startGame();
});

restartButton.addEventListener("click", () => {
  resetGame();
});

toggleMenuButton.addEventListener("click", toggleMenu);
closeMenuButton.addEventListener("click", toggleMenu);

upgradePanel.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof HTMLButtonElement)) return;
  const key = target.dataset.action;
  if (key) {
    applyUpgrade(key);
  }
});

window.addEventListener("keydown", (event) => {
  const key = event.key.toLowerCase();
  if (key === "m") {
    toggleMenu();
    return;
  }
  if (event.code === "Space") {
    event.preventDefault();
    handleDash();
  }
  if (key in keys) keys[key] = true;
});

window.addEventListener("keyup", (event) => {
  const key = event.key.toLowerCase();
  if (key in keys) keys[key] = false;
});

initWaterParticles();
updateHud();
updateDashHud();
updateEvolutionHud();
updateUpgradePanel();
requestAnimationFrame(loop);
