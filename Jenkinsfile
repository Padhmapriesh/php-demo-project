pipeline {
    agent any

    environment {
        AWS_ACCOUNT_ID = '137583030820'
        AWS_REGION     = 'us-east-1'
        REPO_URI       = "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/php-demo-ecr"
        IMAGE_TAG      = "${env.GIT_COMMIT[0..6]}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build') {
            steps {
                sh """
                    docker build -t ${REPO_URI}:${IMAGE_TAG} -t ${REPO_URI}:latest .
                """
            }
        }

        stage('Push to ECR') {
            steps {
                sh """
                    aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com
                    docker push ${REPO_URI}:${IMAGE_TAG}
                    docker push ${REPO_URI}:latest
                """
            }
        }

        stage('Deploy via CodeDeploy') {
            steps {
                sh """
                    aws deploy create-deployment \
                        --application-name php-mysql-app \
                        --deployment-group-name php-app-deployment-group \
                        --github-location repository=Padhmapriesh/php-demo-project,commitId=${GIT_COMMIT} \
                        --description "Deploy commit ${IMAGE_TAG} via Jenkins"
                """
            }
        }
    }

    post {
        success {
            echo "Pipeline succeeded — image ${REPO_URI}:${IMAGE_TAG} pushed and deployment triggered."
        }
        failure {
            echo "Pipeline failed — check the stage logs above."
        }
    }
}
