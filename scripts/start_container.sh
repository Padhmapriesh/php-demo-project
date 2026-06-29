#!/bin/bash
set -e

AWS_REGION="us-east-1"
IMAGE_REPO_NAME="php-demo-ecr"

echo "Fetching configuration from SSM Parameter Store..."

# Fetch all values from SSM Parameter Store
DB_HOST=$(aws ssm get-parameter --name "/php-app/DB_HOST" --query "Parameter.Value" --output text --region $AWS_REGION)
DB_NAME=$(aws ssm get-parameter --name "/php-app/DB_NAME" --query "Parameter.Value" --output text --region $AWS_REGION)
DB_USER=$(aws ssm get-parameter --name "/php-app/DB_USER" --query "Parameter.Value" --output text --region $AWS_REGION)
DB_PASS=$(aws ssm get-parameter --name "/php-app/DB_PASS" --with-decryption --query "Parameter.Value" --output text --region $AWS_REGION)
DB_PORT=$(aws ssm get-parameter --name "/php-app/DB_PORT" --query "Parameter.Value" --output text --region $AWS_REGION)

# Derive AWS account ID dynamically — no hardcoding needed
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
REPOSITORY_URI="$AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$IMAGE_REPO_NAME"

echo "Logging in to Amazon ECR..."
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com

echo "Pulling latest image from ECR..."
docker pull $REPOSITORY_URI:latest

echo "Starting container..."
docker run -d \
  --name php-mysql-app \
  --restart unless-stopped \
  -p 80:80 \
  -e DB_HOST="$DB_HOST" \
  -e DB_NAME="$DB_NAME" \
  -e DB_USER="$DB_USER" \
  -e DB_PASS="$DB_PASS" \
  -e DB_PORT="$DB_PORT" \
  $REPOSITORY_URI:latest

echo "Container started successfully."
