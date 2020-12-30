import React, { PureComponent } from "react";
import LoadingGrid from "./LoadingGrid";

import "./PreviewController.css";

// Portrait, 6s
const width = 375;
const height = 667;

const size = {
  portrait: {
    width,
    height
  },
  landscape: {
    width: height,
    height: width
  }
};

export default class PreviewController extends PureComponent {
  constructor(props) {
    super(props);
    this.state = { loading: true };
  }
  postMessage = msg => {
    if (!this._frame) {
      console.log(
        "PreviewController:: this._frame is missing, cannot postMessage",
        this
      );
      return;
    }

    this._frame.contentWindow.postMessage(msg, "*");
  };

  _memoizeFrame = frame => {
    this._frame = frame;

    if (!frame) {
      return;
    }

    const { onFrameLoad } = this.props;

    this._frame.onload = () => {
      this.setState({
        loading: false
      });

      if (onFrameLoad) {
        onFrameLoad();
      }
    };
  };

  render() {
    const { orientation = "portrait", src, style } = this.props;

    const { width, height } = size[orientation];
    const boxStyle = {
      position: "relative",
      overflow: "hidden",
      width,
      height,
      ...style
    };

    const shadowStyle = {
      border: 0,
      borderRadius: 15,
      boxShadow: "0px 8px 24px rgba(0, 0, 0, 0.2)"
    };

    const positioningStyle = {
      position: "absolute",
      top: 0,
      left: 0
    };

    const loadingGrid = (
      <LoadingGrid
        style={{
          width,
          height,
          backgroundColor: "#000",
          ...positioningStyle
        }}
      />
    );

    return (
      <div
        style={this.state.loading ? { ...boxStyle, ...shadowStyle } : { ...boxStyle, overflow: "visible" }}
        className="preview-controller"
      >
        {this.state.loading ? loadingGrid : null}
        <iframe
          src={src}
          seamless
          width={width}
          height={height}
          border={0}
          title="This is a preview of a game controller"
          ref={this._memoizeFrame}
          scrollable="no"
          scrolling="no"
          style={{
            ...boxStyle,
            ...shadowStyle,
            ...positioningStyle,
            visibility: this.state.loading ? "hidden" : "visible"
          }}
        />
      </div>
    );
  }
}
