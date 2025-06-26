#!/bin/bash

CONTAINER_NAME="blur-tools"
IMAGE_NAME="blur-tools-img"

echo "?? Prüfe, ob Container '$CONTAINER_NAME' läuft..."
if [ "$(docker ps -q -f name=$CONTAINER_NAME)" ]; then
    echo "? Stoppe laufenden Container..."
    docker stop $CONTAINER_NAME
fi

if [ "$1" == "--reset" ]; then
    echo "?? --reset erkannt: Entferne bestehenden Container und Image..."
    docker rm $CONTAINER_NAME 2>/dev/null
    docker rmi $IMAGE_NAME 2>/dev/null
fi

echo "?? Baue neues Image..."
docker build -t $IMAGE_NAME .

echo "?? Starte Container..."
docker run --rm -d \
    -p 7860:7860 \
    --name $CONTAINER_NAME \
    $IMAGE_NAME

echo "? Fertig! Interface erreichbar unter http://localhost:7860"