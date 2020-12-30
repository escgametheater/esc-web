import React, { Component } from "react";

import Croppie from "croppie/croppie.js";
import "croppie/croppie.css";
import "./Cropper.css";

export default class Cropper extends Component {
  shouldComponentUpdate(nextProps) {
    if (this.props.data) {
      console.log("CROPPER should update: ", (nextProps.data !== this.props.data), this.props.data.length, this.props.data);
    }
    
    return (nextProps.data !== this.props.data);
  }

  componentDidMount() {
    if (this.props.data) {
      this.renderDataUrlImage(this.props.data);
    }
  }

  componentDidUpdate() {
    if (this.props.data) {
      this.renderDataUrlImage(this.props.data);
    }
  }

  componentWillUnmount() {
    if (this._croppie && this._croppie.destroy) {
      this._croppie.destroy();
    }

    this._croppie._root.removeEventListener("update", this.triggerUpdate);
    this._croppie = {};
  }

  _initialize = root => {
    if (!root) {
      return;
    }

    if (this._croppie && this._croppie.destroy) {
      this._croppie.destroy();
      this._croppie = {};
    }

    this._croppie = new Croppie(root, {
      viewport: {
        width: 256,
        height: 128,
        ...this.props.viewport || {},
      }
    });

    // window._croppie = this._croppie;

    this._croppie._root = root;

    // Render a 1x1 transparent pixel
    // this._croppie.bind({
    //   url: `data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=`
    // });

    root.addEventListener("update", this.triggerUpdate);
  };

  renderDataUrlImage = url => {
    if (!this._croppie) {
      return;
    }

    this._croppie.bind({
      url,
      zoom: 0
    })
  };

  triggerUpdate = () => {
    if (!this.props.onUpdate || !this._croppie) {
      return;
    }

    Promise.all([
      this._croppie.result({type: "base64", size: {width: this.props.properties.minWidth}}),
      this._croppie.result({type: "blob", size: {width: this.props.properties.minWidth}}),
    ])
    .then((images) => {
      console.log("GOT IMAGES FROM TRIGGER UPDATE", images);
      this.props.onUpdate({
        base64: images[0],
        blob: images[1],
      });
    })
    .catch(e => {
      console.error("Cropper: Failed to generate image ", e);
    })
  }

  render() {
    console.log("CROPPER: got props", this.props);

    return (
      <div
        style={this.props.style}
        ref={this._initialize}
        className="cropper"
      />
    );
  }
}
