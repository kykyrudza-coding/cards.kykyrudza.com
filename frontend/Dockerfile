# Build context is the PROJECT ROOT (not frontend/), because the frontend
# build depends on the sibling assets/ directory (npm run assets:sync).
# No nginx — matches this server's existing pattern (see crm's web
# container): a single Node image builds and serves via `vite preview`.

FROM node:22-alpine

WORKDIR /repo
COPY assets ./assets
COPY frontend ./frontend
WORKDIR /repo/frontend
RUN npm ci

ARG VITE_API_URL
ARG VITE_WS_HOST
ARG VITE_WS_PORT
ARG VITE_WS_SCHEME
ARG VITE_REVERB_APP_KEY
ENV VITE_API_URL=$VITE_API_URL \
    VITE_WS_HOST=$VITE_WS_HOST \
    VITE_WS_PORT=$VITE_WS_PORT \
    VITE_WS_SCHEME=$VITE_WS_SCHEME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY

RUN npm run build

EXPOSE 4173
CMD ["npm", "run", "preview", "--", "--host", "0.0.0.0", "--port", "4173"]
