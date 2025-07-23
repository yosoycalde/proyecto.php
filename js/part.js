document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('particleCanvas');

    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resizeCanvas();

    class Particle {
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.vx = (Math.random() - 0.5) * 6;
            this.vy = (Math.random() - 0.5) * 6;
            this.size = Math.random() * 15 + 8;
            this.life = 1.0;
            this.decay = Math.random() * 0.015 + 0.003;

            const colors = [
                '#d11d1dff', '#15daccff', '#6a15daff', '#15d47bff',
                '#fdc91cff', '#dd2eddff', '#3b383bff', '#f56200ff'
            ];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;
            this.life -= this.decay;

            this.vy += 0.05;

            this.vx *= 0.995;
            this.vy *= 0.995;

            if (this.x <= 0 || this.x >= canvas.width) {
                this.vx *= -0.8;
                this.x = Math.max(0, Math.min(canvas.width, this.x));
            }
            if (this.y <= 0 || this.y >= canvas.height) {
                this.vy *= -0.8;
                this.y = Math.max(0, Math.min(canvas.height, this.y));
            }
        }

        draw(ctx) {
            ctx.save();
            ctx.globalAlpha = this.life;

            const gradient = ctx.createRadialGradient(
                this.x, this.y, 0,
                this.x, this.y, this.size * 3
            );
            gradient.addColorStop(0, this.color);
            gradient.addColorStop(1, 'transparent');

            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();
        }

        isDead() {
            return this.life <= 0;
        }
    }

    class ParticleSystem {
        constructor() {
            this.particles = [];
            this.maxParticles = 150;
        }

        addParticle(x, y, count = 1) {
            for (let i = 0; i < count; i++) {
                if (this.particles.length < this.maxParticles) {
                    this.particles.push(new Particle(x, y));
                }
            }
        }

        update() {
            for (let particle of this.particles) {
                particle.update();
            }

            this.particles = this.particles.filter(particle => !particle.isDead());
        }

        draw(ctx) {
            for (let particle of this.particles) {
                particle.draw(ctx);
            }
        }

        addRandomParticles() {
            if (Math.random() < 0.005) {
                this.addParticle(
                    Math.random() * canvas.width,
                    Math.random() * canvas.height,
                    1
                );
            }
        }

        getParticleCount() {
            return this.particles.length;
        }
    }

    const particleSystem = new ParticleSystem();
    let animationId;

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
        gradient.addColorStop(0, 'rgba(102, 126, 234, 0.03)');
        gradient.addColorStop(1, 'rgba(118, 75, 162, 0.03)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        particleSystem.addRandomParticles();


        particleSystem.update();
        particleSystem.draw(ctx);

        animationId = requestAnimationFrame(animate);
    }

    window.addEventListener('resize', resizeCanvas);

    canvas.addEventListener('click', (e) => {
        console.log(' Clic detectado, agregando partículas');
        particleSystem.addParticle(e.clientX, e.clientY, 12); 
    });

    const interactiveArea = document.querySelector('.particle-interactive');
    if (interactiveArea) {
        interactiveArea.addEventListener('click', (e) => {
            console.log(' Área interactiva activada');
            const rect = interactiveArea.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            for (let i = 0; i < 15; i++) {
                particleSystem.addParticle(centerX, centerY, 1);
            }
        });
    }

    let mouseTrail = false;
    canvas.addEventListener('mousemove', (e) => {
        if (mouseTrail && Math.random() < 0.1) {
            particleSystem.addParticle(e.clientX, e.clientY, 1);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 't' || e.key === 'T') {
            mouseTrail = !mouseTrail;
            console.log(`Rastro del mouse: ${mouseTrail ? 'ON' : 'OFF'}`);
        }
    });

    animate();

    // Debug info cada 5 segundos
    setInterval(() => {
        console.log(` Partículas activas: ${particleSystem.getParticleCount()}`);
    }, 5000);
});