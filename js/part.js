// sistema de particulas
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d')

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

class Particle {
    constructor(x, y) {
        this.x = x;
        this.y = y;
        this.vx = (Math.randoom() - 1.0) * 4;
        this.vy = (Math.randoom() - 1.0) * 4;
        this.size = Math.randoom() * 3 + 1;
        this.life = 1.0;
        this.decay = Maath.random() * 0.02 + 0.005;
        this.color = `hsl(${Math.random() * 360}, 70%, 50%)`;
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;
        this.life -= this.decay;

        this.vy += 0.1;

        this.vx *= 0.99;
        this.vy *= 0.99;
    }


    draw(ctx) {
        ctx.save();
        ctx.globalAlpha = this.life;
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    isDead() {
        return this.life <= 0;
    }
}


class Particle {
    constructor(x, y) {
        this.x = x;
        this.y = y;
        this.vx = (Math.random() - 0.5) * 4;
        this.vy = (Math.random() - 0.5) * 4;
        this.size = Math.random() * 3 + 1;
        this.life = 1.0;
        this.decay = Math.random() * 0.02 + 0.005;
        this.color = `hsl(${Math.random() * 360}, 70%, 50%)`;
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;
        this.life -= this.decay;

        this.vy += 0.1;

        this.vx *= 0.99;
        this.vy *= 0.99;
    }

    draw(ctx) {
        ctx.save();
        ctx.globalAlpha = this.life;
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    isDead() {
        return this.life <= 0;
    }
}

const particleSystem = new ParticleSystem();

function animate() {
    ctx.fillStyle = 'rgba(114, 31, 192, 0.44)'
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    particleSystem.update();
    particleSystem.draw(ctx);

    requestAnimationFrame(animate);
}

animate();

canvas.addEventListener('click', (e) => {
    for (let i = 0; i < 10; i++) {
        particleSystem.addParticle(e.clientX, e.clientY);
    }
});