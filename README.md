# PHP + MySQL (RDS) + Docker + EC2 + CI/CD — Full Walkthrough

This project is a minimal PHP app (form → MySQL insert → list) wired up for
Docker, AWS RDS, manual EC2 deployment, and CI/CD via AWS CodePipeline.

## Project layout
```
php-mysql-app/
├── src/
│   ├── index.php      # form + insert + list
│   ├── db.php         # DB connection (reads env vars)
│   └── health.php     # health check endpoint
├── Dockerfile
├── buildspec.yml       # CodeBuild
├── appspec.yml          # CodeDeploy
├── scripts/             # CodeDeploy lifecycle hooks
├── .env.example
└── .gitignore
```

---

## 1. The PHP application
Already written for you in `src/`. It does three things:
- Shows a form (name + message)
- Inserts submissions into a `messages` table (auto-created on first run)
- Lists the last 20 entries

Connection settings come from environment variables (`DB_HOST`, `DB_NAME`,
`DB_USER`, `DB_PASS`, `DB_PORT`) — never hardcode credentials.

---

## 2. Dockerize it
`Dockerfile` uses the official `php:8.2-apache` image, installs the
`mysqli` extension, and copies `src/` into Apache's web root.

Build & test locally (you'll need a reachable MySQL — see step 3, or run a
local MySQL container for a quick test):
```bash
docker build -t php-mysql-app .

# Quick local test against a throwaway MySQL container:
docker network create demo-net
docker run -d --name mysql-test --network demo-net \
  -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=appdb \
  -e MYSQL_USER=admin -e MYSQL_PASSWORD=password mysql:8.0

docker run -d --name php-test --network demo-net -p 8080:80 \
  -e DB_HOST=mysql-test -e DB_NAME=appdb -e DB_USER=admin -e DB_PASS=password \
  php-mysql-app

# Visit http://localhost:8080
```

---

## 3. MySQL on AWS RDS
**Console steps:**
1. RDS → **Create database** → Engine: **MySQL** (8.0).
2. Templates: "Free tier" (for testing) or "Production".
3. Settings: DB instance identifier `php-app-db`, master username `admin`, set a password.
4. Instance class: `db.t3.micro` (free tier) is fine for testing.
5. Connectivity: choose the **VPC** your EC2 instance will live in. Set
   "Public access" → **Yes** only if you need to connect from outside the
   VPC for testing; otherwise **No** (more secure — EC2 reaches it privately).
6. **VPC security group**: create a new one, e.g. `rds-sg`.
7. Initial database name: `appdb`.
8. Create database. Wait ~5–10 min for status **Available**.
9. Copy the **Endpoint** (e.g. `php-app-db.xxxxx.us-east-1.rds.amazonaws.com`) — this is `DB_HOST`.

**Security group rule (critical):**
- Edit `rds-sg` → Inbound rules → Add rule: Type **MySQL/Aurora (3306)**,
  Source = the **security group of your EC2 instance** (not 0.0.0.0/0).
  This lets EC2 talk to RDS without opening the DB to the internet.

**Equivalent CLI:**
```bash
aws rds create-db-instance \
  --db-instance-identifier php-app-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --master-username admin \
  --master-user-password "YOUR_STRONG_PASSWORD" \
  --allocated-storage 20 \
  --db-name appdb \
  --vpc-security-group-ids sg-xxxxxxxx \
  --no-publicly-accessible
```

Update `.env` / container env vars with the real endpoint, then re-test
locally if you have network access to RDS (e.g. from a bastion or VPN).

---

## 4. Manual deployment to EC2

**Launch the instance:**
1. EC2 → Launch instance → Amazon Linux 2023, `t2.micro`.
2. Security group: allow **SSH (22)** from your IP, and **HTTP (80)** from `0.0.0.0/0`.
3. Place it in the **same VPC** as your RDS instance (so it can reach it via the SG rule above).
4. Launch with a key pair (e.g. `my-key.pem`).

**Install Docker on EC2:**
```bash
ssh -i my-key.pem ec2-user@<EC2_PUBLIC_IP>

sudo yum update -y
sudo yum install -y docker
sudo systemctl enable --now docker
sudo usermod -aG docker ec2-user
# log out/in for group change to apply, or run: newgrp docker
```

**Transfer files (from your local machine):**
```bash
scp -i my-key.pem -r ./php-mysql-app ec2-user@<EC2_PUBLIC_IP>:/home/ec2-user/
```

**Build & run on EC2:**
```bash
ssh -i my-key.pem ec2-user@<EC2_PUBLIC_IP>
cd php-mysql-app

docker build -t php-mysql-app .

docker run -d --name php-mysql-app -p 80:80 \
  -e DB_HOST="php-app-db.xxxxx.us-east-1.rds.amazonaws.com" \
  -e DB_NAME="appdb" \
  -e DB_USER="admin" \
  -e DB_PASS="YOUR_STRONG_PASSWORD" \
  -e DB_PORT="3306" \
  php-mysql-app
```

---

## 5. Testing
- Open `http://<EC2_PUBLIC_IP>` in a browser → you should see the form.
- Submit a name + message → it inserts a row and the page reloads showing it
  in the "Recent Messages" list (confirms read **and** write to RDS).
- `curl http://<EC2_PUBLIC_IP>/health.php` → should return `OK`.
- Sanity-check directly in MySQL if needed:
  ```bash
  docker exec -it php-mysql-app bash
  # or connect from a machine with DB access:
  mysql -h <rds-endpoint> -u admin -p appdb -e "SELECT * FROM messages;"
  ```

---

## 6. CI/CD — AWS CodePipeline (Option 1)

**a) Push code to GitHub**
```bash
git init
git add .
git commit -m "Initial PHP + MySQL app"
git remote add origin https://github.com/<you>/php-mysql-app.git
git push -u origin main
```

**b) Create an ECR repository** (to store Docker images)
```bash
aws ecr create-repository --repository-name php-mysql-app
```

**c) CodeBuild project**
- Source: your GitHub repo (connect via the GitHub App / OAuth).
- Environment: Managed image, `Amazon Linux 2`, runtime `Standard`,
  **enable "Privileged" mode** (required for Docker builds).
- Environment variables: `AWS_ACCOUNT_ID`, `AWS_DEFAULT_REGION`.
- Buildspec: use `buildspec.yml` from this repo (already included) — it
  logs into ECR, builds the image, tags it, pushes it, and writes
  `imageDetail.json` for the next stage.
- IAM role for CodeBuild needs `AmazonEC2ContainerRegistryPowerUser` (or
  equivalent ECR push permissions).

**d) CodeDeploy application (EC2/On-Premises compute platform)**
- Create application → Compute platform: **EC2/On-premises**.
- Create deployment group → target the EC2 instance via tag (e.g. `Name=php-app-server`).
- Install the **CodeDeploy agent** on the EC2 instance:
  ```bash
  sudo yum install -y ruby wget
  cd /home/ec2-user
  wget https://aws-codedeploy-<region>.s3.<region>.amazonaws.com/latest/install
  chmod +x ./install
  sudo ./install auto
  sudo systemctl start codedeploy-agent
  ```
- Attach an IAM **instance role** to EC2 with `AmazonEC2RoleforAWSCodeDeploy`
  and ECR pull permissions (`AmazonEC2ContainerRegistryReadOnly`).
- Use `appspec.yml` (included) + the hook scripts in `scripts/` — these
  stop the old container, pull the new image from ECR, and run it.
  **Important:** edit the placeholders in `scripts/start_container.sh`
  (`AWS_ACCOUNT_ID`, `AWS_REGION`, RDS endpoint/credentials) — ideally
  pull secrets from **AWS SSM Parameter Store** instead of hardcoding.

**e) CodePipeline**
1. Source stage: GitHub (your repo, branch `main`), trigger on push.
2. Build stage: the CodeBuild project from (c).
3. Deploy stage: CodeDeploy application/deployment group from (d).
4. Save & release — pushing to `main` now triggers: build image → push to
   ECR → CodeDeploy pulls and restarts the container on EC2 automatically.

---

## Security notes (read before treating this as production-ready)
- Never commit real DB credentials — use `.env.example` as a template only.
- For real deployments, store secrets in **AWS Secrets Manager** or **SSM
  Parameter Store**, not in `start_container.sh` directly.
- Keep RDS `publicly accessible = No` and restrict access via security groups.
- Consider an Application Load Balancer + Auto Scaling Group instead of a
  single EC2 instance for anything beyond a demo.
