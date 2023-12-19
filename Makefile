buildimage:
    docker build -t vitexsoftware/discomp2abraflexi:latest .

buildx:
    docker buildx build . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/discomp2abraflexi:latest

drun:
    docker run --env-file .env vitexsoftware/discomp2abraflexi:latest
