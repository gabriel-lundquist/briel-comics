# escape=`

FROM nginx:latest
COPY ["./draft_site", "/usr/share/nginx/html/draft_site/"]